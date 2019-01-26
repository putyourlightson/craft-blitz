<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\services;

use craft\base\Component;
use putyourlightson\blitz\Blitz;

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
     * Clears the cache.
     */
    public function clear()
    {
        Blitz::$plugin->cacheStorage->deleteAll();

        Blitz::$plugin->cachePurger->purgeAll();

        // Fire an 'afterClearCache' event
        if ($this->hasEventHandlers(self::EVENT_AFTER_CLEAR_CACHE)) {
            $this->trigger(self::EVENT_AFTER_CLEAR_CACHE);
        }
    }
}
