<?php

namespace Oro\Bundle\ThemeMigrationBundle\Command;

use Oro\Bundle\ThemeMigrationBundle\Parser\DatagridMigrationFileParser;
use Oro\Bundle\ThemeMigrationBundle\Parser\ThemeExtractFileParser;
use Oro\Bundle\ThemeMigrationBundle\Util\ThemeExtractBundleUtils;
use Oro\Bundle\ThemeMigrationBundle\Util\ThemeExtractFileConfig;
use Oro\Bundle\ThemeMigrationBundle\Util\ThemeExtractFileSystemUtils;
use Oro\Bundle\ThemeMigrationBundle\Util\ThemeExtractPathConfig;
use Oro\Component\Config\ResourcesContainer;
use Oro\Component\Layout\Extension\Theme\ResourceProvider\ThemeResourceProvider;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;

/**
 * Command for extracting layout files for a theme and move them to a newly generated bundle
 */
class ThemeMigrationCommand extends Command
{
    protected static $defaultName = 'oro:theme:migrate';

    protected SymfonyStyle $io;

    protected ThemeExtractPathConfig $pathConfig;
    protected ThemeExtractBundleUtils $bundleUtils;
    protected ThemeExtractFileParser $extractFileParser;
    protected ThemeExtractFileSystemUtils $fsUtils;
    protected ThemeResourceProvider $themeResourceProvider;
    protected DatagridMigrationFileParser $gridParser;

    public function __construct(
        ThemeResourceProvider $themeResourceProvider,
        ThemeExtractPathConfig $pathConfig,
        ThemeExtractFileSystemUtils $fsUtils,
        ThemeExtractFileParser $extractFileParser,
        ThemeExtractBundleUtils $bundleUtils,
        DatagridMigrationFileParser $gridParser
    ) {
        $this->themeResourceProvider = $themeResourceProvider;
        $this->pathConfig = $pathConfig;
        $this->fsUtils = $fsUtils;
        $this->extractFileParser = $extractFileParser;
        $this->bundleUtils = $bundleUtils;
        $this->gridParser = $gridParser;
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('oro:theme:migrate')
            ->setDescription('Extract a given theme from all the existing packages');
    }

    /**
     * {@inheritdoc}
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);

        $this->io->title(
            \sprintf('Extracting layout files for theme %s into a single bundle.', $this->pathConfig::DEFAULT_THEME_ID)
        );

        $themeIdHierarchy = $this->findThemeHierarchy($this->pathConfig::DEFAULT_THEME_ID);
        $this->pathConfig->init($themeIdHierarchy);

        $this->generateThemeBundle();

        $this->io->warning('Deleting bundle content for a new clean merge..');
        $this->fsUtils->remove($this->pathConfig->getWithBundlePath('Resources/public'));
        $this->fsUtils->remove($this->pathConfig->getWithBundlePath('Resources/views'));

        $this->copyPublicBundleAssets();
        $targetGridPath = $this->gridParser->migrateGrids($this->pathConfig::DEFAULT_THEME_ID);
        if ($this->io->isVerbose()) {
            $this->io->info('New datagrid file was generated at ' . $targetGridPath);
        }

        $resources = $this->themeResourceProvider->loadAndGetConfig(new ResourcesContainer());
        $this->extractTheme($resources, $themeIdHierarchy, $this->pathConfig::DEFAULT_THEME_ID);

        if ($this->io->isVeryVerbose() && $this->fsUtils->getFileLog()) {
            $this->io->info('Following file actions were performed:');
            foreach ($this->fsUtils->getFileLog() as $action => $logs) {
                $this->io->section(strtoupper($action) . ": ");
                foreach ($logs as $key => $log) {
                    if (is_string($key)) {
                        $this->io->writeln('⌈' . $key);
                        $this->io->writeln('| ');
                        $this->io->writeln('⌊⌲' . $log);
                        $this->io->writeln(
                            '<fg=green>-------------------</>'
                        );
                    } else {
                        $this->io->writeln($log);
                    }
                }
            }
        }

        $this->showSuccessMessage();

        return self::SUCCESS;
    }

    protected function generateThemeBundle(): void
    {
        $bundleClassFullPath = \sprintf(
            '%s/%s.php',
            $this->pathConfig->getNewThemeBundlePath(),
            $this->pathConfig->getThemeBundleClassName(),
        );
        $this->fsUtils->copy(__DIR__ . '/ThemeResources/OroThemeDefaultBundle.php', $bundleClassFullPath, true);
        $content = $this->fsUtils->open($bundleClassFullPath);
        $content = str_replace('ThemeDefaultBundle', $this->pathConfig->getThemeBundleFolderName(), $content);
        $this->fsUtils->dumpFile($bundleClassFullPath, $content);

        $bundleYmlFullPath = $this->pathConfig->getNewThemeBundlePath() . '/Resources/config/oro/bundles.yml';
        $this->fsUtils->copy(__DIR__ . '/ThemeResources/bundles.yml', $bundleYmlFullPath, true);
        $content = $this->fsUtils->open($bundleYmlFullPath);
        $content = str_replace('ThemeDefaultBundle', $this->pathConfig->getThemeBundleFolderName(), $content);
        $this->fsUtils->dumpFile($bundleYmlFullPath, $content);
    }

    protected function extractTheme(
        array $resources,
        array $themeIdHierarchy,
        string $themeId
    ): void {
        // parse all resource files and copy/merge them into bundle folder
        $this->copyAndParseYmlFiles($resources[$themeId], $themeId);

        [$themeInfo, $originalParent] = $this->parseThemeYml($themeId);
        $this->parseAssetConfigFiles($themeId);

        if ($this->io->isVerbose()) {
            $this->io->note(
                sprintf(
                    'Searching and parsing "%s" files in %s',
                    '*.scss',
                    $this->pathConfig->getNewThemeAssetFullPath()
                )
            );
        }
        $this->searchAndParseAssetFilesByPattern('*.scss');

        if (isset($themeInfo['parent'])) {
            $this->extractTheme($resources, $themeIdHierarchy, $originalParent);
        }
    }

    /**
     * Copies a found layout file into the target bundle, keeping the original bundle directory structure of the file
     */
    protected function copyAndParseYmlFiles(string|array $sourceFile, string $themeId): void
    {
        if (is_array($sourceFile)) {
            foreach ($sourceFile as $subSourceFile) {
                $this->copyAndParseYmlFiles($subSourceFile, $themeId);
            }
            return;
        }
        if (!$this->fsUtils->exists($sourceFile)) {
            $this->io->warning(sprintf('File path %s seems to be an unusual layout config file', $sourceFile));
            return;
        }
        $target = substr($sourceFile, strpos($sourceFile, $this->pathConfig->getSearchFolderPattern($themeId)));
        $target = str_replace(
            'layouts/' . $themeId,
            'layouts/' . $this->pathConfig->generateNewThemeId($themeId),
            $target
        );
        $targetFullPath = $this->pathConfig->getNewThemeBundlePath() . '/' . $target;

        $bundleAlias = $this->pathConfig->extractBundleAliasFromFullPath($sourceFile);
        [$targetFileName, $newTargetFileName, $newTargetFullPath] = $this->fsUtils->copyWithBundleSuffix(
            $sourceFile,
            $targetFullPath,
            $bundleAlias
        );

        $this->extractFileParser->parseFile($sourceFile, $newTargetFullPath, new ThemeExtractFileConfig(), $themeId);
    }

    protected function copyPublicBundleAssets(): void
    {
        foreach ($this->bundleUtils->getBundleAliases() as $bundleAlias) {
            // skip self bundle
            if (str_starts_with($bundleAlias, 'orothemedefault')) {
                continue;
            }
            $dirPath = $this->pathConfig->getAppPublicBundleFullPath() . '/' . $bundleAlias;
            if (!$this->fsUtils->exists($dirPath)) {
                continue;
            }
            $targetFullPath = $this->pathConfig->getNewThemeAssetFullPath($bundleAlias);
            $this->fsUtils->copy($dirPath, $targetFullPath);
        }
    }

    protected function searchAndParseAssetFilesByPattern(string $pattern): void
    {
        $finder = new Finder();
        $finder->files()->in($this->pathConfig->getNewThemeAssetFullPath())->name($pattern);

        foreach ($finder as $file) {
            $fileContent = $this->fsUtils->open($file->getPathname());
            $fileContent = \preg_replace(
                $this->pathConfig->getAssetRefRegex(),
                $this->pathConfig->getNewThemeAssetBundleAlias() . '/$0',
                $fileContent
            );
            $this->fsUtils->dumpFile($file->getPathname(), $fileContent);
        }
    }

    /**
     * Finds all the parents of the given themeId
     */
    protected function findThemeHierarchy(string $currentThemeId, array $themeIds = []): array
    {
        if (!$themeIds) {
            $themeIds = [$currentThemeId];
        }
        $filePattern = sprintf('Resources/views/layouts/%s/theme.yml', $currentThemeId);
        foreach ($this->extractFileParser->getConfigFileContent($filePattern) as $themeInfo) {
            if (isset($themeInfo['parent'])) {
                $themeIds[] = $themeInfo['parent'];
                $this->findThemeHierarchy($themeInfo['parent'], $themeIds);
                return $themeIds;
            }
        }

        return $themeIds;
    }

    protected function parseThemeYml(string $themeId): array
    {
        // generate final theme.yml file. First search for existing theme definition files, and merge them
        $this->extractFileParser->copyAndParseAssetConfigFile(
            $this->pathConfig->getSearchFolderPattern($themeId) . '/theme.yml',
            $this->pathConfig->getAssetRefRegex(),
            true,
            $themeId
        );

        // Get merged config file and combine with static theme.yml
        $targetFullPath = $this->pathConfig->getWithBundlePath(
            $this->pathConfig->getNewThemeLayoutFolderPattern($themeId) . '/theme.yml'
        );
        $mergedYmlArray = Yaml::parseFile($targetFullPath);
        $sourceFullPath = __DIR__ . '/ThemeResources/' . $this->pathConfig->generateNewThemeId($themeId) . '/theme.yml';
        $originalParent = null;
        if (isset($mergedYmlArray['parent'])) {
            $originalParent = $mergedYmlArray['parent'];
            $mergedYmlArray['parent'] = $this->pathConfig->generateNewThemeId($mergedYmlArray['parent']);
        }
        if ($this->fsUtils->exists($sourceFullPath)) {
            $mergedYmlArray = array_merge(
                $mergedYmlArray,
                Yaml::parseFile($sourceFullPath)
            );
        }
        $this->fsUtils->dumpFile($targetFullPath, Yaml::dump($mergedYmlArray, 100));

        return [$mergedYmlArray, $originalParent];
    }

    /**
     * Combine asset config files from each bundle into single files and parse them
     */
    protected function parseAssetConfigFiles(string $themeId): void
    {
        $additionalResources = [
            'assets.yml' => true,
            'page_templates.yml' => true,
            'images.yml' => true,
            'screens.yml' => true,
            'widgets.yml' => true,
            'jsmodules.yml' => false,
        ];
        foreach ($additionalResources as $resourceName => $replaceRegex) {
            $filePattern = sprintf('%s/config/' . $resourceName, $this->pathConfig->getSearchFolderPattern($themeId));
            if ($this->io->isVerbose()) {
                $this->io->note(sprintf('Combining "%s" config files into single file...', $filePattern));
            }

            $this->extractFileParser->copyAndParseAssetConfigFile(
                $filePattern,
                $this->pathConfig->getAssetRefRegex(),
                $replaceRegex,
                $themeId
            );
        }
    }

    protected function showSuccessMessage(): void
    {
        $this->io->success('Migration successful.');

        $this->io->note(
            sprintf(
                'new bundle for theme "%s" was generated at %s',
                ThemeExtractPathConfig::DEFAULT_THEME_ID,
                $this->pathConfig->getNewThemeBundlePath()
            )
        );

        $this->io->warning(
            'Custom storefront datagrids should be manually moved from ' .
            '<AnyBundle>/Resources/config/oro/datagrids.yml to ' .
            '<AnyBundle>/Resources/views/layouts/<themeid>/config/datagrids.yml'
        );

        $this->io->writeln('');
        $this->io->writeln('');
        $this->io->writeln(
            'Please remove the app\'s cache and run <comment>oro:assets:install (--symlink)</comment>' .
            'to publish the new files into public folder.'
        );
        $this->io->writeln('');
        $this->io->writeln('');
    }
}
