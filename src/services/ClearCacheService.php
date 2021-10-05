<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\services;

use craft\base\Component;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\events\RefreshCacheEvent;
use putyourlightson\blitz\helpers\SiteUriHelper;
use putyourlightson\blitz\models\SiteUriModel;

class ClearCacheService extends Component
{
    // Constants
    // =========================================================================

    /**
     * @event RefreshCacheEvent
     */
    const EVENT_BEFORE_CLEAR_CACHE = 'beforeClearCache';

    /**
     * @event RefreshCacheEvent
     */
    const EVENT_AFTER_CLEAR_CACHE = 'afterClearCache';

    /**
     * @event RefreshCacheEvent
     */
    const EVENT_BEFORE_CLEAR_ALL_CACHE = 'beforeClearAllCache';

    /**
     * @event RefreshCacheEvent
     */
    const EVENT_AFTER_CLEAR_ALL_CACHE = 'afterClearAllCache';

    // Public Methods
    // =========================================================================

    /**
     * Clears the cache given an array of site URIs.
     *
     * @param SiteUriModel[] $siteUris
     */
    public function clearUris(array $siteUris)
    {
        $event = new RefreshCacheEvent(['siteUris' => $siteUris]);
        $this->trigger(self::EVENT_BEFORE_CLEAR_CACHE, $event);

        if (!$event->isValid) {
            return;
        }

        Blitz::$plugin->cacheStorage->deleteUris($event->siteUris);

        Blitz::$plugin->cachePurger->purgeUris($event->siteUris);

        if ($this->hasEventHandlers(self::EVENT_AFTER_CLEAR_CACHE)) {
            $this->trigger(self::EVENT_AFTER_CLEAR_CACHE, $event);
        }
    }

    /**
     * Clears the cache for a given site.
     *
     * @param int $siteId
     */
    public function clearSite(int $siteId)
    {
        $siteUris = SiteUriHelper::getSiteUrisForSite($siteId);

        $this->clearUris($siteUris);
    }

    /**
     * Clears the entire cache.
     */
    public function clearAll()
    {
        $event = new RefreshCacheEvent();
        $this->trigger(self::EVENT_BEFORE_CLEAR_ALL_CACHE, $event);

        if (!$event->isValid) {
            return;
        }

        Blitz::$plugin->cacheStorage->deleteAll();

        Blitz::$plugin->cachePurger->purgeAll();

        if ($this->hasEventHandlers(self::EVENT_AFTER_CLEAR_ALL_CACHE)) {
            $this->trigger(self::EVENT_AFTER_CLEAR_ALL_CACHE, $event);
        }
    }
}
