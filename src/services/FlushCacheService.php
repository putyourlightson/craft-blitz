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
     * Flushes the cache.
     */
    public function flush()
    {
        CacheRecord::deleteAll();

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
     * Deletes cache records given a site URI.
     *
     * @param SiteUriModel $siteUri
     */
    public function deleteSiteUri(SiteUriModel $siteUri)
    {
        CacheRecord::deleteAll($siteUri->toArray());
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
