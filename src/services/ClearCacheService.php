<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\services;

use craft\base\Component;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\helpers\SiteUriHelper;
use putyourlightson\blitz\models\SiteUriModel;

class ClearCacheService extends Component
{
    // Constants
    // =========================================================================

    /**
     * @event Event
     */
    const EVENT_AFTER_CLEAR_CACHE = 'afterClearCache';

    // Public Methods
    // =========================================================================

    /**
     * Clears the entire cache.
     */
    public function clearAll()
    {
        Blitz::$plugin->cacheStorage->deleteAll();

        Blitz::$plugin->cachePurger->purgeAll();

        // Fire an 'afterClearCache' event
        if ($this->hasEventHandlers(self::EVENT_AFTER_CLEAR_CACHE)) {
            $this->trigger(self::EVENT_AFTER_CLEAR_CACHE);
        }
    }

    /**
     * Clears the cache for a given site.
     *
     * @param int $siteId
     */
    public function clearSite(int $siteId)
    {
        $siteUris = SiteUriHelper::getSiteSiteUris($siteId);

        Blitz::$plugin->cacheStorage->deleteUris($siteUris);

        Blitz::$plugin->cachePurger->purgeUris($siteUris);

        // Fire an 'afterClearCache' event
        if ($this->hasEventHandlers(self::EVENT_AFTER_CLEAR_CACHE)) {
            $this->trigger(self::EVENT_AFTER_CLEAR_CACHE);
        }
    }

    /**
     * Clears the cache given an array of site URIs.
     *
     * @param SiteUriModel[] $siteUris
     */
    public function clearUris(array $siteUris)
    {
        Blitz::$plugin->cacheStorage->deleteUris($siteUris);

        Blitz::$plugin->cachePurger->purgeUris($siteUris);

        // Fire an 'afterClearCache' event
        if ($this->hasEventHandlers(self::EVENT_AFTER_CLEAR_CACHE)) {
            $this->trigger(self::EVENT_AFTER_CLEAR_CACHE);
        }
    }
}
