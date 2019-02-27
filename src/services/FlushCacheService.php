<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\services;

use craft\base\Component;
use putyourlightson\blitz\models\SiteUriModel;
use putyourlightson\blitz\records\CacheRecord;
use putyourlightson\blitz\records\ElementQueryRecord;

class FlushCacheService extends Component
{
    // Constants
    // =========================================================================

    /**
     * @event Event
     */
    const EVENT_AFTER_FLUSH_CACHE = 'afterFlushCache';

    // Public Methods
    // =========================================================================

    /**
     * Flushes the entire cache.
     */
    public function flushAll()
    {
        CacheRecord::deleteAll();

        $this->runGarbageCollection();

        // Fire an 'afterFlushCache' event
        if ($this->hasEventHandlers(self::EVENT_AFTER_FLUSH_CACHE)) {
            $this->trigger(self::EVENT_AFTER_FLUSH_CACHE);
        }
    }

    /**
     * Flushes the cache for a given site.
     *
     * @param int $siteId
     */
    public function flushSite(int $siteId)
    {
        CacheRecord::deleteAll(['siteId' => $siteId]);

        $this->runGarbageCollection();

        // Fire an 'afterFlushCache' event
        if ($this->hasEventHandlers(self::EVENT_AFTER_FLUSH_CACHE)) {
            $this->trigger(self::EVENT_AFTER_FLUSH_CACHE);
        }
    }

    /**
     * Deletes cache records given an array of cache IDs.
     *
     * @param int[] $cacheIds
     */
    public function deleteCacheIds(array $cacheIds)
    {
        CacheRecord::deleteAll(['id' => $cacheIds]);
    }

    /**
     * Runs garbage collection.
     */
    public function runGarbageCollection()
    {
        // Get and delete element query records without an associated element query cache
        $elementQueryRecordIds = ElementQueryRecord::find()
            ->select('id')
            ->joinWith('elementQueryCaches')
            ->where(['cacheId' => null])
            ->column();

        ElementQueryRecord::deleteAll(['id' => $elementQueryRecordIds]);
    }
}
