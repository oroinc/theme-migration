<?php

namespace Oro\Bundle\ThemeMigrationBundle\Util;

use Symfony\Component\Filesystem\Filesystem;

/**
 * Utils for file system related logic
 */
class ThemeExtractFileSystemUtils
{
    protected array $fileLog = [];
    protected Filesystem $fs;

    public function __construct(
        private readonly ThemeExtractPathConfig $pathConfig,
        private readonly ThemeExtractBundleUtils $bundleUtils,
    ) {
        $this->fs = new Filesystem();
    }

    /**
     * Rename and copy a bundle alias referenced file into the new bundle.
     * Example:
     * @OroUI/layouts/default/dialog/dialog.html.twig =>
     *   => @OroThemeDefault/layouts/default_51/dialog/500_dialog_OroUI.html.twig
     */
    public function copyBundleRefFile(string $bundleAlias, string $referencePath, string $themeId): array
    {
        $bundlePath = $this->bundleUtils->getBundlePathByName($bundleAlias);

        $referenceFullPath = $this->pathConfig->getBundlePathWithSourceLayoutPattern(
            $bundlePath,
            $referencePath,
            $themeId
        );
        $targetPath = str_replace('/' . $this->pathConfig->getLayoutSourceThemePattern($themeId), '', $referencePath);
        $targetFullPath = $this->pathConfig->getNewThemeLayoutFullPath($themeId) . '/' . $targetPath;
        [$targetFileName, $newTargetFileName, $targetFullPath] = $this->copyWithBundleSuffix(
            $referenceFullPath,
            $targetFullPath,
            $bundleAlias,
        );

        $targetPath = str_replace($targetFileName, $newTargetFileName, $targetPath);
        $newReferencePath = $this->pathConfig->getLayoutPathInBundleRefFormat(
            $targetPath,
            $this->pathConfig->generateNewThemeId($themeId)
        );

        return [$referenceFullPath, $newReferencePath, $targetFullPath];
    }

    /**
     * Rename and copy a relative referenced file into the new bundle.
     * Example:
     *   dialog/dialog.html.twig =>
     *   => dialog/500_dialog_OroUI.html.twig
     */
    public function copyBundleRelativeRefFile(
        string $referencePath,
        string $sourceFullPath,
        string $themeId,
    ): array {
        $sourceFullPath = $this->pathConfig->resolveDirNavigationInPath($sourceFullPath);
        $bundleAlias = $this->pathConfig->extractBundleAliasFromFullPath($sourceFullPath);

        $searchFolder = $this->pathConfig->getSearchFolderPattern($themeId);
        $referenceFullPath = substr(
            $sourceFullPath,
            0,
            strpos(
                $sourceFullPath,
                $this->pathConfig->getSearchFolderPattern($themeId)
            ) + strlen($searchFolder)
        );
        $sourcePath = str_replace($referenceFullPath, '', $sourceFullPath);
        $relativeFolderPrefix = substr($sourcePath, 0, strrpos($sourcePath, '/') + 1);
        $referenceFullPath = $referenceFullPath . $relativeFolderPrefix . $referencePath;

        $targetPath = $relativeFolderPrefix . $referencePath;
        $targetFullPath = $this->pathConfig->getNewThemeLayoutFullPath($themeId)
            . $this->pathConfig->getWithDirPrefix($targetPath);
        [$targetFileName, $newTargetFileName, $targetFullPath] = $this->copyWithBundleSuffix(
            $referenceFullPath,
            $targetFullPath,
            $bundleAlias,
        );
        $referenceFullPath = $this->pathConfig->resolveDirNavigationInPath($referenceFullPath);

        $targetPath = str_replace($targetFileName, $newTargetFileName, $targetPath);
        $newReferencePath = $this->pathConfig->resolveDirNavigationInPath(
            $this->pathConfig->getLayoutPathInBundleRefFormat(
                $targetPath,
                $this->pathConfig->generateNewThemeId($themeId)
            )
        );

        return [$referenceFullPath, $newReferencePath, $targetFullPath];
    }

    public function copyWithBundleSuffix(
        string $sourceFile,
        string $targetFullPath,
        string $bundleAlias,
    ): array {
        $targetFileName = substr($targetFullPath, strrpos($targetFullPath, '/') + 1);
        $nameWithoutExtension = substr($targetFileName, 0, strpos($targetFileName, '.'));

        $bundleAlias = str_replace('Bundle', '', $bundleAlias);
        $bundleName = $bundleAlias . 'Bundle';
        $bundlePriority = $this->bundleUtils->getPriority($bundleName) ?? '500';

        $newTargetFileName = $bundlePriority . '_' . $nameWithoutExtension . '_' . $bundleAlias;

        $newTargetFileName = str_replace(
            $nameWithoutExtension,
            $newTargetFileName,
            $targetFileName
        );
        $newTargetFullPath = str_replace($targetFileName, $newTargetFileName, $targetFullPath);
        $newTargetFullPath = $this->pathConfig->resolveDirNavigationInPath($newTargetFullPath);

        $this->copy($sourceFile, $newTargetFullPath);

        return [
            $targetFileName,
            $newTargetFileName,
            $newTargetFullPath
        ];
    }

    public function copy(string $sourceFullPath, string $targetFullPath, bool $overwrite = false): void
    {
        $sourceFullPath = $this->pathConfig->resolveDirNavigationInPath($sourceFullPath);
        $targetFullPath = $this->pathConfig->resolveDirNavigationInPath($targetFullPath);

        if (!$this->fs->exists($sourceFullPath)) {
            throw new \Exception(sprintf('Could not find file on path %s', $sourceFullPath));
        }

        $sourceFullPath = str_replace('/', DIRECTORY_SEPARATOR, $sourceFullPath);
        $targetFullPath = str_replace('/', DIRECTORY_SEPARATOR, $targetFullPath);

        $exists = false;
        if (is_dir($sourceFullPath)) {
            $this->fs->mirror($sourceFullPath, $targetFullPath);
        } else {
            $exists = $this->fs->exists($targetFullPath);
            if ($overwrite) {
                $this->fs->copy($sourceFullPath, $targetFullPath, $overwrite);
            }
        }

        // Log action
        if ($pos = strpos($targetFullPath, 'ThemeMigrationBundle')) {
            $filePath = substr($targetFullPath, $pos);
        } else {
            $filePath = $targetFullPath;
        }
        if ($pos = strpos($sourceFullPath, '/package/')) {
            $key = substr($sourceFullPath, $pos);
        } else {
            $key = $sourceFullPath;
        }

        if ($exists) {
            $this->fileLog['warning'][$key] = 'Target file already exists, skipping copy: ' . $targetFullPath;
        } else {
            $this->fileLog['copied'][$key] = $filePath;
        }
    }

    public function remove(string $targetFullPath): void
    {
        $targetFullPath = str_replace('/', DIRECTORY_SEPARATOR, $targetFullPath);
        $this->fs->remove($targetFullPath);

        // Log action
        if ($pos = strpos($targetFullPath, 'ThemeMigrationBundle')) {
            $filePath = substr($targetFullPath, $pos);
        } elseif ($pos = strpos($targetFullPath, '/package/')) {
            $filePath = substr($targetFullPath, $pos);
        } else {
            $filePath = $targetFullPath;
        }

        $this->fileLog['removed'][] = $filePath;
    }

    public function open(string $targetFullPath): false|string
    {
        $targetFullPath = str_replace('/', DIRECTORY_SEPARATOR, $targetFullPath);

        return file_get_contents($targetFullPath);
    }

    public function dumpFile(string $targetFullPath, string $fileContent): void
    {
        $this->fs->dumpFile($targetFullPath, $fileContent);
    }

    public function exists(string $path): bool
    {
        return $this->fs->exists($path);
    }

    public function getFileLog(): array
    {
        return $this->fileLog;
    }
}
