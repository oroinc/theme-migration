<?php

namespace Oro\Bundle\ThemeMigrationBundle\Util;

/**
 * Configuration class for theme extraction
 */
class ThemeExtractFileConfig
{
    public static array $ALLOWED_FILE_EXTENSIONS_TO_PARSE = [
        'twig',
        'yml',
    ];

    private bool $parseTemplateRefs = true;
    private bool $parseRelativeTemplateRefs = true;
    private bool $parseAssetRefs = true;

    public function isParseTemplateRefs(): bool
    {
        return $this->parseTemplateRefs;
    }

    public function setParseTemplateRefs(bool $parseTemplateRefs): ThemeExtractFileConfig
    {
        $this->parseTemplateRefs = $parseTemplateRefs;
        return $this;
    }

    public function isParseRelativeTemplateRefs(): bool
    {
        return $this->parseRelativeTemplateRefs;
    }

    public function setParseRelativeTemplateRefs(bool $parseRelativeTemplateRefs): ThemeExtractFileConfig
    {
        $this->parseRelativeTemplateRefs = $parseRelativeTemplateRefs;
        return $this;
    }

    public function isParseAssetRefs(): bool
    {
        return $this->parseAssetRefs;
    }

    public function setParseAssetRefs(bool $parseAssetRefs): ThemeExtractFileConfig
    {
        $this->parseAssetRefs = $parseAssetRefs;
        return $this;
    }

    public function getAllowedFileExtensions(): array
    {
        return static::$ALLOWED_FILE_EXTENSIONS_TO_PARSE;
    }
}
