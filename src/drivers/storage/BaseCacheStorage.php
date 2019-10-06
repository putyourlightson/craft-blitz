<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\drivers\storage;

use craft\base\SavableComponent;

/**
 * @property string $utilityHtml
 */
abstract class BaseCacheStorage extends SavableComponent implements CacheStorageInterface
{
    /**
     * @inheritdoc
     */
    public function getUtilityHtml(): string
    {
        return '';
    }
}
