<?php

namespace Oro\Bundle\ThemeMigrationBundle\Util;

/**
 * Array utils for theme extraction logic
 */
class ThemeExtractArrayUtils
{
    public function mergeArraysReplaceStrings($array1, $array2): array
    {
        if ($this->isArrayFlat($array1) && $this->isArrayFlat($array2)) {
            return array_merge($array1, $array2);
        }
        foreach ($array2 as $key => $value) {
            if (is_array($value) && isset($array1[$key]) && is_array($array1[$key])) {
                // Recursively merge sub-arrays
                $array1[$key] = $this->mergeArraysReplaceStrings($array1[$key], $value);
            } else {
                // Replace value if it's a string
                if (is_string($value)) {
                    $array1[$key] = $value;
                } else {
                    // Merge arrays if not a string
                    $array1[$key] = $value;
                }
            }
        }

        return $array1;
    }

    protected function isArrayFlat($array): bool
    {
        if (!is_array($array)) {
            return false;
        }
        foreach ($array as $key => $value) {
            if (!is_scalar($value) && !is_int($key)) {
                return false;
            }
        }

        return true;
    }
}
