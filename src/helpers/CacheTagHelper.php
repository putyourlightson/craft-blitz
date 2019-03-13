<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\helpers;

use putyourlightson\campaign\helpers\StringHelper;

class CacheTagHelper
{
    /**
     * Returns tags from a given value.
     *
     * @param string|string[]|null
     *
     * @return string[]
     */
    public static function getTags($value): array
    {
        if ($value === null) {
            return [];
        }

        if (is_string($value)) {
            return StringHelper::split($value);
        }

        return $value;
    }
}