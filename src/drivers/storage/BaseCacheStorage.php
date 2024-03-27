<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\drivers\storage;

use craft\base\SavableComponent;
use putyourlightson\blitz\models\SiteUriModel;

/**
 * @property-read string $utilityHtml
 * @property-read string $widgetHtml
 */
abstract class BaseCacheStorage extends SavableComponent implements CacheStorageInterface
{
    use CacheStorageTrait;

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
     * @const string
     */
    public const ENCODING = 'gzip';

    /**
     * @var bool Whether cached values should be compressed using gzip.
     * @since 4.5.0
     */
    public bool $compressCachedValues = false;

    /**
     * @inheritdoc
     */
    public function getCompressed(SiteUriModel $siteUri): string
    {
        return '';
    }

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

    /**
     * @inheritdoc
     */
    public function canCompressCachedValues(): bool
    {
        return $this->compressCachedValues && function_exists('gzencode');
    }
}
