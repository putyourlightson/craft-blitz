<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\services;

use Craft;
use craft\base\Component;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\events\RefreshCacheEvent;
use putyourlightson\blitz\helpers\SiteUriHelper;
use putyourlightson\blitz\models\SiteUriModel;
use putyourlightson\blitz\records\CacheRecord;
use putyourlightson\blitz\records\ElementQueryRecord;
use yii\db\Exception;

class FlushCacheService extends Component
{
    // Constants
    // =========================================================================

    /**
     * @event RefreshCacheEvent
     */
    const EVENT_BEFORE_FLUSH_CACHE = 'beforeFlushCache';

    /**
     * @event RefreshCacheEvent
     */
    const EVENT_AFTER_FLUSH_CACHE = 'afterFlushCache';

    /**
     * @event RefreshCacheEvent
     */
    const EVENT_BEFORE_FLUSH_ALL_CACHE = 'beforeFlushAllCache';

    /**
     * @event RefreshCacheEvent
     */
    const EVENT_AFTER_FLUSH_ALL_CACHE = 'afterFlushAllCache';

    // Public Methods
    // =========================================================================

    /**
     * Flushes cache records given an array of site URIs.
     *
     * @param SiteUriModel[] $siteUris
     */
    public function flushUris(array $siteUris)
    {
        $event = new RefreshCacheEvent(['siteUris' => $siteUris]);
        $this->trigger(self::EVENT_BEFORE_FLUSH_CACHE, $event);

        if (!$event->isValid) {
            return;
        }

        $mutex = Craft::$app->getMutex();

        foreach ($event->siteUris as $siteUri) {
            $lockName = GenerateCacheService::MUTEX_LOCK_NAME_SITE_URI.':'.$siteUri->siteId.'-'.$siteUri->uri;

            if (!$mutex->acquire($lockName, Blitz::$plugin->settings->mutexTimeout)) {
                continue;
            }

            CacheRecord::deleteAll([
                'siteId' => $siteUri->siteId,
                'uri' => $siteUri->uri,
            ]);
        }

        $this->runGarbageCollection();

        if ($this->hasEventHandlers(self::EVENT_AFTER_FLUSH_CACHE)) {
            $this->trigger(self::EVENT_AFTER_FLUSH_CACHE, $event);
        }
    }

    /**
     * Flushes the cache for a given site.
     *
     * @param int $siteId
     */
    public function flushSite(int $siteId)
    {
        $siteUris = SiteUriHelper::getSiteSiteUris($siteId);
        $this->flushUris($siteUris);
    }

    /**
     * Flushes the entire cache.
     */
    public function flushAll()
    {
        $event = new RefreshCacheEvent();
        $this->trigger(self::EVENT_BEFORE_FLUSH_ALL_CACHE, $event);

        if (!$event->isValid) {
            return;
        }

        CacheRecord::deleteAll();

        $this->runGarbageCollection();

        if ($this->hasEventHandlers(self::EVENT_AFTER_FLUSH_ALL_CACHE)) {
            $this->trigger(self::EVENT_AFTER_FLUSH_ALL_CACHE, $event);
        }
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

        // Check if auto increment values should be reset
        if (CacheRecord::find()->count() == 0) {
            $this->_resetAutoIncrement(CacheRecord::tableName());
        }

        if (ElementQueryRecord::find()->count() == 0) {
            $this->_resetAutoIncrement(ElementQueryRecord::tableName());
        }
    }

    // Private Methods
    // =========================================================================

    /**
     * Resets auto increment values of the given table.
     *
     * @param string $table
     */
    private function _resetAutoIncrement(string $table)
    {
        $db = Craft::$app->getDb();
        $dbDriver = $db->getDriverName();
        $sql = '';

        if ($dbDriver == 'mysql') {
            $sql = 'ALTER TABLE '.$table.' AUTO_INCREMENT = 1';
        }
        else if ($dbDriver == 'postgres') {
            $sql = 'ALTER SEQUENCE '.$table.'_id_seq RESTART WITH 1';
        }

        if ($sql) {
            try {
                $db->createCommand($sql)->execute();
            }
            catch (Exception $e) { }
        }
    }
}
