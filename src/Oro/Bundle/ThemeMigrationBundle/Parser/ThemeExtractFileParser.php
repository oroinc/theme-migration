<?php

namespace Oro\Bundle\ThemeMigrationBundle\Parser;

use Oro\Bundle\ThemeMigrationBundle\Util\ThemeExtractArrayUtils;
use Oro\Bundle\ThemeMigrationBundle\Util\ThemeExtractBundleUtils;
use Oro\Bundle\ThemeMigrationBundle\Util\ThemeExtractFileConfig;
use Oro\Bundle\ThemeMigrationBundle\Util\ThemeExtractFileSystemUtils;
use Oro\Bundle\ThemeMigrationBundle\Util\ThemeExtractPathConfig;
use Symfony\Component\Yaml\Yaml;

/**
 * Helpers for parsing theme related files
 */
class ThemeExtractFileParser
{
    protected array $parsedFiles = [];

    public function __construct(
        private readonly ThemeExtractPathConfig $pathConfig,
        private readonly ThemeExtractBundleUtils $bundleUtils,
        private readonly ThemeExtractFileSystemUtils $fsUtil,
        private readonly ThemeExtractArrayUtils $arrayUtils,
    ) {
    }

    /**
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function parseFile(
        string $sourceFullPath,
        string $targetFullPath,
        ThemeExtractFileConfig $fileConfig,
        string $themeId
    ): void {
        if (isset($this->parsedFiles[$targetFullPath])) {
            if ($this->parsedFiles[$targetFullPath] !== $sourceFullPath) {
                throw new \LogicException(
                    \sprintf(
                        "An already parsed files (%s) is being parsed again with a different source path. 
                        Old source path: %s ;, New source path: %s",
                        $targetFullPath,
                        $this->parsedFiles[$targetFullPath],
                        $sourceFullPath
                    )
                );
            }
            return;
        }
        $this->parsedFiles[$targetFullPath] = $sourceFullPath;

        $extension = substr($targetFullPath, strrpos($targetFullPath, '.') + 1);
        if (!in_array($extension, $fileConfig->getAllowedFileExtensions())) {
            return;
        }

        $fileContent = $this->fsUtil->open($sourceFullPath);

        if ($fileConfig->isParseTemplateRefs()) {
            // parse bundle references like '@OroUI/layouts/default/block_types.html.twig'
            if (preg_match_all($this->pathConfig->getTemplateRefRegex(), $fileContent, $matches)) {
                foreach ($matches[0] as $refNumber => $subReference) {
                    [
                        $subReferenceFullPath,
                        $newSubReferencePath,
                        $newSubReferenceFullPath
                    ] = $this->fsUtil->copyBundleRefFile(
                        $matches[1][$refNumber],
                        $matches[4][$refNumber],
                        $matches[3][$refNumber],
                    );
                    $fileContent = str_replace($subReference, $newSubReferencePath, $fileContent);

                    // detect self-reference. It causes infinite loop because of sub parsing each reference
                    // stop parsing if the reference is self-reference
                    if (str_contains($sourceFullPath, $matches[2][$refNumber])) {
                        continue;
                    }
                    $this->parseFile(
                        $subReferenceFullPath,
                        $newSubReferenceFullPath,
                        $fileConfig,
                        $matches[3][$refNumber]
                    );
                }
            }
        }

        if ($fileConfig->isParseRelativeTemplateRefs()) {
            // parse template relative references like 'analytics4.html.twig'
            if ($extension === 'yml'
                && preg_match_all($this->pathConfig->getTemplateRelativeRefRegex(), $fileContent, $matches)
            ) {
                foreach ($matches[0] as $refNumber => $subReference) {
                    if (strpos($subReference, $this->pathConfig->generateNewThemeId($themeId))) {
                        continue;
                    }

                    [$subReferenceFullPath, $newSubReferencePath, $newSubReferenceFullPath] = $this->fsUtil
                        ->copyBundleRelativeRefFile(
                            $matches[0][$refNumber],
                            $sourceFullPath,
                            $themeId
                        );
                    $fileContent = str_replace($subReference, $newSubReferencePath, $fileContent);

                    $this->parseFile($subReferenceFullPath, $newSubReferenceFullPath, $fileConfig, $themeId);
                }
            }
        }

        if ($fileConfig->isParseAssetRefs() && $extension !== 'yml') {
            // parse asset references
            $fileContent = \preg_replace(
                $this->pathConfig->getAssetBundleRefRegex(),
                $this->pathConfig->getNewThemeAssetBundleAlias() . '/$0',
                $fileContent,
            );
            $fileContent = \preg_replace(
                $this->pathConfig->getTemplateAssetRefRegex(),
                $this->pathConfig->getNewThemeAssetBundleAlias() . '/$0',
                $fileContent
            );
        }

        $this->fsUtil->dumpFile($targetFullPath, $fileContent);
    }

    public function getConfigFileContent(string $filePattern): \Generator
    {
        foreach ($this->bundleUtils->getBundleAliases() as $bundleName => $bundleAlias) {
            if ('app.kernel' === $bundleAlias) {
                continue;
            }
            $bundlePath = $this->bundleUtils->getBundlePathByName($bundleName);
            $configFullPath = $bundlePath . '/' . $filePattern;
            if (!$this->fsUtil->exists($configFullPath)) {
                continue;
            }

            yield Yaml::parseFile($configFullPath);
        }
    }

    public function copyAndParseAssetConfigFile(
        string $filePattern,
        string $regex,
        bool $replaceRegex,
        string $themeId
    ): void {
        $configArray = [];
        foreach ($this->bundleUtils->getBundleAliases() as $bundleName => $bundleAlias) {
            if ('app.kernel' === $bundleAlias) {
                continue;
            }
            $bundlePath = $this->bundleUtils->getBundlePathByName($bundleName);
            $configFullPath = $bundlePath . '/' . $filePattern;
            if (!$this->fsUtil->exists($configFullPath)) {
                continue;
            }

            $ymlArray = Yaml::parseFile($configFullPath);
            $configArray = $this->arrayUtils->mergeArraysReplaceStrings($configArray, $ymlArray);
        }
        $filePattern = $this->pathConfig->replaceThemeName($filePattern, $themeId);
        $targetConfigFileFullPattern = $this->pathConfig->getWithBundlePath($filePattern);

        $configArray = $this->updateSlickCarouselRefs($filePattern, $configArray);

        $this->fsUtil->dumpFile($targetConfigFileFullPattern, Yaml::dump($configArray, 100));

        if ($replaceRegex) {
            $fileContent = $this->fsUtil->open($targetConfigFileFullPattern);
            $fileContent = \preg_replace(
                $regex,
                $this->pathConfig->getNewThemeAssetBundleAlias() . '/$0',
                $fileContent,
            );
            $this->fsUtil->dumpFile($targetConfigFileFullPattern, $fileContent);
        }
    }

    /**
     * Updates slick-carousel references in assets.yml and jsmodules.yml to use @oroinc/slick-carousel fork
     */
    private function updateSlickCarouselRefs(string $filePattern, array $configArray): array
    {
        if (\str_ends_with($filePattern, 'assets.yml')) {
            // replace value "~slick-carousel/slick/slick.scss" in $configArray
            // with "~@oroinc/slick-carousel/slick/slick.scss"
            $key = array_search('~slick-carousel/slick/slick.scss', $configArray['styles']['inputs'], true);
            if ($key !== false) {
                $configArray['styles']['inputs'][$key] = '~@oroinc/slick-carousel/slick/slick.scss';
            }
        }
        if (\str_ends_with($filePattern, 'jsmodules.yml')) {
            // replace value for key "slick$" in $configArray with "@oroinc/slick-carousel/slick/slick"
            if (isset($configArray['aliases']['slick$'])) {
                $configArray['aliases']['slick$'] = '@oroinc/slick-carousel/slick/slick';
            }
        }
        return $configArray;
    }
}
