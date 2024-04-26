<?php

namespace Oro\Bundle\ThemeMigrationBundle\Util;

use Oro\Component\Config\CumulativeResourceManager;

/**
 * Utils for theme extraction bundle related logic
 */
class ThemeExtractBundleUtils
{
    protected array $bundlePriority;

    protected array $bundlePathMapping = [];
    protected array $bundleAliases;
    protected CumulativeResourceManager $resourceManager;

    public function __construct()
    {
        $this->resourceManager = CumulativeResourceManager::getInstance();
        $this->init();
    }

    public function getBundleAliases(): array
    {
        return $this->bundleAliases;
    }

    public function getPriority(string $bundleAlias): ?string
    {
        return $this->bundlePriority[strtolower($bundleAlias)] ?? null;
    }

    public function getBundlePathByName(string $bundleName): string
    {
        $bundleName = strtolower($bundleName);
        if (!str_contains($bundleName, 'bundle')) {
            $bundleName .= 'bundle';
        }
        if (!isset($this->bundlePathMapping[$bundleName])) {
            throw new \Exception(sprintf('Bundle with alias %s could not be found', $bundleName));
        }

        return $this->bundlePathMapping[$bundleName];
    }

    protected function init(): void
    {
        $mapping = $this->getKernelBundleMapping();
        foreach ($mapping as $bundleName => $bundleFullPath) {
            $this->bundlePathMapping[$bundleName] = $bundleFullPath;
        }

        // generate a priority map for the bundles
        $priority = 1;
        foreach ($this->bundlePathMapping as $bundleName => $bundleFullPath) {
            $this->bundleAliases[$bundleName] = str_replace('bundle', '', $bundleName);
            $this->bundlePriority[strtolower($bundleName)] = str_pad($priority, 3, '0', STR_PAD_LEFT);
            $priority++;
        }
    }

    protected function getKernelBundleMapping(): array
    {
        $bundles = $this->resourceManager->getBundles();
        $mapping = [];
        foreach ($bundles as $bundleName => $bundleClass) {
            $mapping[strtolower($bundleName)] = $this->resourceManager->getBundleDir($bundleClass);
        }

        return $mapping;
    }
}
