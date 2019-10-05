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

    // Public Methods
    // =========================================================================

    /**
     * Clears the cache given an array of site URIs.
     *
     * @param SiteUriModel[] $siteUris
     */
    public function clearUris(array $siteUris)
    {
        $event = $this->onBeforeClear(['siteUris' => $siteUris]);

        if (!$event->isValid) {
            return;
        }

        Blitz::$plugin->cacheStorage->deleteUris($event->siteUris);

        Blitz::$plugin->cachePurger->purgeUris($event->siteUris);

        // Fire an 'afterClearCache' event
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
        $event = $this->onBeforeClear(['siteId' => $siteId]);

        if (!$event->isValid) {
            return;
        }

        $siteUris = SiteUriHelper::getSiteSiteUris($event->siteId);

        $this->clearUris($siteUris);

        // Fire an 'afterClearCache' event
        if ($this->hasEventHandlers(self::EVENT_AFTER_CLEAR_CACHE)) {
            $this->trigger(self::EVENT_AFTER_CLEAR_CACHE, $event);
        }
    }

    /**
     * Clears the entire cache.
     */
    public function clearAll()
    {
        $event = $this->onBeforeClear();

        if (!$event->isValid) {
            return;
        }

        Blitz::$plugin->cacheStorage->deleteAll();

        Blitz::$plugin->cachePurger->purgeAll();

        // Fire an 'afterClearCache' event
        if ($this->hasEventHandlers(self::EVENT_AFTER_CLEAR_CACHE)) {
            $this->trigger(self::EVENT_AFTER_CLEAR_CACHE, $event);
        }
    }

    // Protected Methods
    // =========================================================================

    /**
     * Fires an onBeforeClear event.
     *
     * @param array $config
     *
     * @return RefreshCacheEvent
     */
    protected function onBeforeClear(array $config)
    {
        $event = new RefreshCacheEvent($config);
        $this->trigger(self::EVENT_BEFORE_CLEAR_CACHE, $event);

        return $event;
    }
}
