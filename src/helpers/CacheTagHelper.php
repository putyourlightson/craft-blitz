<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\helpers;

use Craft;
use craft\base\Element;
use putyourlightson\blitz\models\SiteUriModel;
use putyourlightson\blitz\records\CacheRecord;
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