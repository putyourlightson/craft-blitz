<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\services;

use craft\base\Component;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\events\RefreshCacheEvent;
use putyourlightson\blitz\events\RefreshCacheTagsEvent;
use putyourlightson\blitz\helpers\SiteUriHelper;
use putyourlightson\blitz\models\SiteUriModel;

class ClearCacheService extends Component
{
    /**
     * @event RefreshCacheEvent
     */
    public const EVENT_BEFORE_CLEAR_CACHE = 'beforeClearCache';

    /**
     * @event RefreshCacheEvent
     */
    public const EVENT_AFTER_CLEAR_CACHE = 'afterClearCache';

    /**
     * @event RefreshCacheTagsEvent
     */
    public const EVENT_BEFORE_CLEAR_CACHE_TAGS = 'beforeClearCacheTags';

    /**
     * @event RefreshCacheTagsEvent
     */
    public const EVENT_AFTER_CLEAR_CACHE_TAGS = 'afterClearCacheTags';

    /**
     * @event RefreshCacheEvent
     */
    public const EVENT_BEFORE_CLEAR_ALL_CACHE = 'beforeClearAllCache';

    /**
     * @event RefreshCacheEvent
     */
    public const EVENT_AFTER_CLEAR_ALL_CACHE = 'afterClearAllCache';

    /**
     * Clears the cache given an array of site URIs.
     *
     * @param SiteUriModel[] $siteUris
     */
    public function clearUris(array $siteUris): void
    {
        $event = new RefreshCacheEvent(['siteUris' => $siteUris]);
        $this->trigger(self::EVENT_BEFORE_CLEAR_CACHE, $event);

        if (!$event->isValid) {
            return;
        }

        Blitz::$plugin->cacheStorage->deleteUris($event->siteUris);

        if ($this->hasEventHandlers(self::EVENT_AFTER_CLEAR_CACHE)) {
            $this->trigger(self::EVENT_AFTER_CLEAR_CACHE, $event);
        }
    }

    /**
     * Clears cached URLs.
     *
     * @param string[] $urls
     *
     * @since 4.11.0
     */
    public function clearCachedUrls(array $urls): void
    {
        $siteUris = SiteUriHelper::getSiteUrisFromUrls($urls);

        $this->clearUris($siteUris);
    }

    /**
     * Clears the cache given an array of tags.
     *
     * @param string[] $tags
     *
     * @since 4.11.0
     */
    public function clearCacheTags(array $tags): void
    {
        $event = new RefreshCacheTagsEvent(['tags' => $tags]);
        $this->trigger(self::EVENT_BEFORE_CLEAR_CACHE_TAGS, $event);

        if (!$event->isValid) {
            return;
        }

        $cacheIds = Blitz::$plugin->cacheTags->getCacheIds($event->tags);
        $siteUris = SiteUriHelper::getCachedSiteUris($cacheIds);
        $this->clearUris($siteUris);

        if ($this->hasEventHandlers(self::EVENT_AFTER_CLEAR_CACHE_TAGS)) {
            $this->trigger(self::EVENT_AFTER_CLEAR_CACHE_TAGS, $event);
        }
    }

    /**
     * Clears the cache for a given site.
     */
    public function clearSite(int $siteId): void
    {
        $siteUris = SiteUriHelper::getSiteUrisForSite($siteId);

        $this->clearUris($siteUris);
    }

    /**
     * Clears the entire cache.
     */
    public function clearAll(): void
    {
        $event = new RefreshCacheEvent();
        $this->trigger(self::EVENT_BEFORE_CLEAR_ALL_CACHE, $event);

        if (!$event->isValid) {
            return;
        }

        Blitz::$plugin->cacheStorage->deleteAll();

        if ($this->hasEventHandlers(self::EVENT_AFTER_CLEAR_ALL_CACHE)) {
            $this->trigger(self::EVENT_AFTER_CLEAR_ALL_CACHE, $event);
        }
    }
}
