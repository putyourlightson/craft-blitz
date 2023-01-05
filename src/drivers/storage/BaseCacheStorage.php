<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\drivers\storage;

use craft\base\SavableComponent;

/**
 * @property-read string $utilityHtml
 */
abstract class BaseCacheStorage extends SavableComponent implements CacheStorageInterface
{
    /**
     * @event RefreshCacheEvent The event that is triggered before URIs are deleted.
     */
    public const EVENT_BEFORE_DELETE_URIS = 'beforeDeleteUris';

    /**
     * @event RefreshCacheEvent The event that is triggered after URIs are deleted.
     */
    public const EVENT_AFTER_DELETE_URIS = 'afterDeleteUris';

    /**
     * @event RefreshCacheEvent The event that is triggered before all URIs are deleted.
     */
    public const EVENT_BEFORE_DELETE_ALL = 'beforeDeleteAll';

    /**
     * @event RefreshCacheEvent The event that is triggered after all URIs are deleted.
     */
    public const EVENT_AFTER_DELETE_ALL = 'afterDeleteAll';

    /**
     * @inheritdoc
     */
    public function getUtilityHtml(): string
    {
        return '';
    }

    /**
     * @inheritdoc
     */
    public function getWidgetHtml(): string
    {
        return '';
    }
}
