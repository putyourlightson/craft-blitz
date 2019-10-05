<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\services;

use Craft;
use craft\base\Component;
use putyourlightson\blitz\events\RefreshCacheEvent;
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

    // Public Methods
    // =========================================================================

    /**
     * Flushes cache records given an array of site URIs.
     *
     * @param SiteUriModel[] $siteUris
     */
    public function flushUris(array $siteUris)
    {
        $event = $this->onBeforeFlush(['siteUris' => $siteUris]);

        if (!$event->isValid) {
            return;
        }

        foreach ($event->siteUris as $siteUri) {
            CacheRecord::deleteAll([
                'siteId' => $siteUri->siteId,
                'uri' => $siteUri->uri,
            ]);
        }

        $this->runGarbageCollection();

        // Fire an 'afterFlushCache' event
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
        $event = $this->onBeforeFlush(['siteId' => $siteId]);

        if (!$event->isValid) {
            return;
        }

        CacheRecord::deleteAll(['siteId' => $siteId]);

        $this->runGarbageCollection();

        // Fire an 'afterFlushCache' event
        if ($this->hasEventHandlers(self::EVENT_AFTER_FLUSH_CACHE)) {
            $this->trigger(self::EVENT_AFTER_FLUSH_CACHE, $event);
        }
    }

    /**
     * Flushes the entire cache.
     */
    public function flushAll()
    {
        $event = $this->onBeforeFlush();

        if (!$event->isValid) {
            return;
        }

        CacheRecord::deleteAll();

        $this->runGarbageCollection();

        // Fire an 'afterFlushCache' event
        if ($this->hasEventHandlers(self::EVENT_AFTER_FLUSH_CACHE)) {
            $this->trigger(self::EVENT_AFTER_FLUSH_CACHE, $event);
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

    // Protected Methods
    // =========================================================================

    /**
     * Triggers the on before event.
     *
     * @param array $config
     *
     * @return RefreshCacheEvent
     */
    protected function onBeforeFlush(array $config)
    {
        $event = new RefreshCacheEvent($config);

        if ($this->hasEventHandlers(self::EVENT_BEFORE_FLUSH_CACHE)) {
            $this->trigger(self::EVENT_BEFORE_FLUSH_CACHE, $event);
        }

        return $event;
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
