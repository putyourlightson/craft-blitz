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
    // Constants
    // =========================================================================

    /**
     * @event RefreshCacheEvent
     */
    const EVENT_BEFORE_DELETE_URIS = 'beforeDeleteUris';

    /**
     * @event RefreshCacheEvent
     */
    const EVENT_AFTER_DELETE_URIS = 'afterDeleteUris';

    /**
     * @event RefreshCacheEvent
     */
    const EVENT_BEFORE_DELETE_ALL = 'beforeDeleteAll';

    /**
     * @event RefreshCacheEvent
     */
    const EVENT_AFTER_DELETE_ALL = 'afterDeleteAll';

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function getUtilityHtml(): string
    {
        return '';
    }
}
