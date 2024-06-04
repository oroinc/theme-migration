<?php

namespace Oro\Bundle\ThemeMigrationBundle\Parser;

use Oro\Bundle\ThemeMigrationBundle\Util\ThemeExtractFileSystemUtils;
use Oro\Bundle\ThemeMigrationBundle\Util\ThemeExtractPathConfig;
use Oro\Component\Config\Loader\Factory\CumulativeConfigLoaderFactory;
use Oro\Component\Config\ResourcesContainer;
use Oro\Component\PhpUtils\ArrayUtil;
use Symfony\Component\Yaml\Yaml;

/**
 * Parse all existing datagrids and extract selected grids into a separate file
 */
class DatagridMigrationFileParser
{
    public function __construct(
        private readonly ThemeExtractPathConfig $pathConfig,
        private readonly ThemeExtractFileSystemUtils $fsUtils,
        private readonly array $gridsToMigrate = []
    ) {
    }

    public function migrateGrids(string $themeId): string
    {
        $resourcesContainer = new ResourcesContainer();
        $cumulativeConfigLoader = CumulativeConfigLoaderFactory::create(
            'grid',
            'Resources/views/layouts/' . $themeId . '/config/datagrids.yml'
        );
        $resources = $cumulativeConfigLoader->load($resourcesContainer);
        $gridConfig = [];
        foreach ($resources as $resource) {
            foreach ($this->gridsToMigrate as $gridToMigrate) {
                if (!isset($resource->data['datagrids'][$gridToMigrate])) {
                    continue;
                }
                $content = $resource->data['datagrids'][$gridToMigrate];

                $gridConfig[$gridToMigrate] = ArrayUtil::arrayMergeRecursiveDistinct(
                    $gridConfig[$gridToMigrate] ?? [],
                    $content
                );
            }
        }
        $yml = Yaml::dump(['datagrids' => $gridConfig], 100);
        $targetFullPath = $this->pathConfig->getNewThemeGridFullPath($themeId);
        $this->fsUtils->dumpFile($targetFullPath, $yml);

        return $targetFullPath;
    }
}
