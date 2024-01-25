<?php

namespace Oro\Bundle\ThemeMigrationBundle\Util;

use Oro\Component\Config\CumulativeResourceManager;
use Symfony\Component\Filesystem\Path;

/**
 * Contains configuration values and helpers for theme extraction
 */
class ThemeExtractPathConfig
{
    public const DEFAULT_THEME_ID = 'default';
    public const THEME_PARENT_REGEX = '/(^parent:\s*)' . self::DEFAULT_THEME_ID . '$/m';
    public const BUNDLE_NAME_REGEX = 'oro[a-z\d\_\-\*]+\/';
    public const ASSET_REFERENCE_REGEX = '/(?<!\@)(?<!\^)(?<!%s\/)' . self::BUNDLE_NAME_REGEX . '(?!oro)/';
    public const ASSET_BUNDLE_REFERENCE_REGEX = '/(?<=bundles\/)([a-z]+\/%s)\//';
    public const LAYOUT_FOLDER_PATTERN = 'layouts/%s';
    public const LAYOUT_PUBLIC_FOLDER_PATTERN = 'Resources/public';
    public const RESOURCES_TEMPLATE_FOLDER_PATTERN = 'Resources/views';
    public const TEMPLATE_ASSET_REFERENCE_REGEX = '/(?<=bundles\/)' . self::BUNDLE_NAME_REGEX . '(?!oro)/';
    public const TEMPLATE_REFERENCE_REGEX = '/@(.*)\/(layouts\/(%s)\/([a-zA-Z\-\_\d+\/\.]*))/';
    public const TEMPLATE_RELATIVE_REFERENCE_REGEX = "/(?<=')(?<!@)[\.a-zA-Z\d+\-\_\/]*\.twig(?=')/";
    public const BUNDLE_PATH_REGEX =
        '/(?P<prefix>[a-z\d\_\-\*]+)(\/Bundle\/|\/Bridge\/)?(?P<bundleName>[a-z\d\_\-\*]*)(?=\/Resources\/)/i';

    protected string $appVersion;
    protected string $appFullPath;
    protected string $appPublicBundleFullPath;
    protected string $assetRefRegex;
    protected string $assetBundleRefRegex;
    protected string $templateRefRegex;
    protected string $templateRelativeRefRegex;
    protected string $newThemeAssetBundleAlias;
    protected string $newThemeAssetFullPath;
    protected string $newThemeBundleAlias;
    protected string $newThemeBundlePath;
    protected string $newThemeBundleRefAlias;
    protected string $templateAssetRefRegex;

    public function __construct()
    {
        $this->appFullPath = CumulativeResourceManager::getInstance()->getAppRootDir();
        $this->appVersion = '50';
    }

    public function init(array $themeIdHierarchy): void
    {
        $this->appPublicBundleFullPath = $this->appFullPath . '/public/bundles';
        $themeIdConcat = implode('|', $themeIdHierarchy);

        $this->assetBundleRefRegex = sprintf(static::ASSET_BUNDLE_REFERENCE_REGEX, $themeIdConcat);

        // NEW THEME related config
        $this->newThemeBundlePath = $this->appFullPath . '/src/Oro/Bundle/' . $this->getThemeBundleFolderName();
        $this->newThemeBundleAlias =
            'Oro'
            . substr(
                $this->newThemeBundlePath,
                strrpos($this->newThemeBundlePath, '/') + 1
            );
        $this->newThemeBundleRefAlias = '@' . rtrim($this->newThemeBundleAlias, 'Bundle');
        $this->newThemeAssetBundleAlias = strtolower(rtrim($this->newThemeBundleAlias, 'Bundle'));
        $this->newThemeAssetFullPath = $this->newThemeBundlePath . '/' . static::LAYOUT_PUBLIC_FOLDER_PATTERN;

        $this->assetRefRegex = sprintf(static::ASSET_REFERENCE_REGEX, $this->newThemeAssetBundleAlias);

        $this->templateAssetRefRegex = static::TEMPLATE_ASSET_REFERENCE_REGEX;
        $this->templateRefRegex = sprintf(static::TEMPLATE_REFERENCE_REGEX, $themeIdConcat);
        $this->templateRelativeRefRegex = static::TEMPLATE_RELATIVE_REFERENCE_REGEX;
    }

    public function getAssetBundleRefRegex(): string
    {
        return $this->assetBundleRefRegex;
    }

    public function getAssetRefRegex(): string
    {
        return $this->assetRefRegex;
    }

    public function getAppPublicBundleFullPath(): string
    {
        return $this->appPublicBundleFullPath;
    }

    public function getLayoutSourceThemePattern(string $themeId): string
    {
        return sprintf(static::LAYOUT_FOLDER_PATTERN, $themeId);
    }

    public function getWithBundlePath(string $pathToAppend = null): string
    {
        $pathToAppend = $pathToAppend ? '/' . $pathToAppend : '';

        return $this->newThemeBundlePath . $pathToAppend;
    }

    public function replaceThemeName(string $pattern, string $themeId): string
    {
        return str_replace($themeId, $this->generateNewThemeId($themeId), $pattern);
    }

    public function getLayoutPathInBundleRefFormat(string $filePath, string $newThemeId): string
    {
        return sprintf(
            '%s/' . static::LAYOUT_FOLDER_PATTERN . '%s',
            $this->newThemeBundleRefAlias,
            $newThemeId,
            $this->getWithDirPrefix($filePath)
        );
    }

    public function extractBundleAliasFromFullPath(string $fullPath): string
    {
        if (preg_match(static::BUNDLE_PATH_REGEX, $fullPath, $matches)) {
            if (!str_contains($matches['bundleName'], 'Bundle')) {
                if (str_contains($matches[0], 'Bridge')) {
                    return $matches['prefix'] . $matches['bundleName'] . 'Bridge';
                }
                return $matches['prefix'];
            }
            return $matches['prefix'] . $matches['bundleName'];
        }

        throw new \Exception(\sprintf('Could not extract bundle information for full path %s', $fullPath));
    }

    public function getBundlePathWithSourceLayoutPattern(
        string $bundlePath,
        string $filePath,
        string $themeId = null
    ): string {
        return $bundlePath . '/' . $this->getSearchFolderPattern($themeId) . '/' . $filePath;
    }

    public function getWithDirPrefix(string $path): string
    {
        $prefix = $path[0] === '/' ? '' : '/';

        return $prefix . $path;
    }

    public function getNewThemeAssetFullPath(string $targetPath = null): string
    {
        $targetPath = $targetPath ? '/' . $targetPath : '';

        return $this->newThemeAssetFullPath . $targetPath;
    }

    public function getBundlessConfigFullPath(string $filePattern, string $themeId): string
    {
        return \sprintf(
            '%s/templates/layouts/%s/%s',
            $this->appFullPath,
            $themeId,
            $filePattern
        );
    }

    public function getBundledFullPath(string $bundlePath, string $themeId, string $filePattern = null): string
    {
        $filePattern = $filePattern ? '/' . $filePattern : '';

        return \sprintf('%s/Resources/views/layouts/%s%s', $bundlePath, $themeId, $filePattern);
    }

    /**
     * Resolve relative directory navigation Example: aaa/bbb/../ccc into aaa/ccc
     */
    public function resolveDirNavigationInPath(string $path): string
    {
        return Path::canonicalize($path);
    }

    public function generateParentThemeName(string $parentName): string
    {
        return $parentName . '_' . $this->appVersion;
    }

    public function generateNewThemeId(string $themeId): string
    {
        return $themeId . '_' . $this->appVersion;
    }

    public function getThemeBundleFolderName(): string
    {
        return 'ThemeDefault' . $this->appVersion . 'Bundle';
    }

    public function getThemeBundleClassName(): string
    {
        return 'Oro' . $this->getThemeBundleFolderName();
    }

    public function getTemplateRefRegex(): string
    {
        return $this->templateRefRegex;
    }

    public function getTemplateRelativeRefRegex(): string
    {
        return $this->templateRelativeRefRegex;
    }

    public function getNewThemeAssetBundleAlias(): string
    {
        return $this->newThemeAssetBundleAlias;
    }

    public function getNewThemeBundlePath(): string
    {
        return $this->newThemeBundlePath;
    }

    public function getNewThemeLayoutFolderPattern(string $themeId): string
    {
        $themeId = $this->generateNewThemeId($themeId);
        return static::RESOURCES_TEMPLATE_FOLDER_PATTERN
            . '/'
            . sprintf(static::LAYOUT_FOLDER_PATTERN, $themeId);
    }

    public function getNewThemeLayoutFullPath(string $themeId): string
    {
        return $this->newThemeBundlePath . '/' . $this->getNewThemeLayoutFolderPattern($themeId);
    }

    public function getTemplateAssetRefRegex(): string
    {
        return $this->templateAssetRefRegex;
    }

    public function getSearchFolderPattern(string $themeId): string
    {
        return static::RESOURCES_TEMPLATE_FOLDER_PATTERN
            . '/'
            . sprintf(static::LAYOUT_FOLDER_PATTERN, $themeId);
    }

    public function getNewThemeGridFullPath(string $themeId): string
    {
        return \sprintf(
            '%s/%s/config/datagrids.yml',
            $this->newThemeBundlePath,
            $this->getSearchFolderPattern(
                $this->generateNewThemeId($themeId)
            )
        );
    }
}
