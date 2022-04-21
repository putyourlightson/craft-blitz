<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\services;

use Craft;
use craft\base\Component;
use Psr\Log\LogLevel;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\events\RefreshCacheEvent;
use putyourlightson\blitz\helpers\SiteUriHelper;
use putyourlightson\blitz\models\SiteUriModel;
use putyourlightson\blitz\records\CacheRecord;
use putyourlightson\blitz\records\ElementQueryRecord;
use yii\db\Exception;

class FlushCacheService extends Component
{
    /**
     * @event RefreshCacheEvent
     */
    public const EVENT_BEFORE_FLUSH_CACHE = 'beforeFlushCache';

    /**
     * @event RefreshCacheEvent
     */
    public const EVENT_AFTER_FLUSH_CACHE = 'afterFlushCache';

    /**
     * @event RefreshCacheEvent
     */
    public const EVENT_BEFORE_FLUSH_ALL_CACHE = 'beforeFlushAllCache';

    /**
     * @event RefreshCacheEvent
     */
    public const EVENT_AFTER_FLUSH_ALL_CACHE = 'afterFlushAllCache';

    /**
     * Flushes cache records given an array of site URIs.
     *
     * @param SiteUriModel[] $siteUris
     */
    public function flushUris(array $siteUris): void
    {
        $event = new RefreshCacheEvent(['siteUris' => $siteUris]);
        $this->trigger(self::EVENT_BEFORE_FLUSH_CACHE, $event);

        if (!$event->isValid) {
            return;
        }

        $mutex = Craft::$app->getMutex();
        $lockName = GenerateCacheService::MUTEX_LOCK_NAME_CACHE_RECORDS;

        if (!$mutex->acquire($lockName, Blitz::$plugin->settings->mutexTimeout)) {
            return;
        }

        foreach ($event->siteUris as $siteUri) {
            CacheRecord::deleteAll([
                'siteId' => $siteUri->siteId,
                'uri' => $siteUri->uri,
            ]);
        }

        $this->runGarbageCollection();

        $mutex->release($lockName);

        if ($this->hasEventHandlers(self::EVENT_AFTER_FLUSH_CACHE)) {
            $this->trigger(self::EVENT_AFTER_FLUSH_CACHE, $event);
        }
    }

    /**
     * Flushes the cache for a given site.
     */
    public function flushSite(int $siteId): void
    {
        $siteUris = SiteUriHelper::getSiteUrisForSite($siteId);
        $this->flushUris($siteUris);
    }

    /**
     * Flushes the entire cache.
     */
    public function flushAll(): void
    {
        $event = new RefreshCacheEvent();
        $this->trigger(self::EVENT_BEFORE_FLUSH_ALL_CACHE, $event);

        if (!$event->isValid) {
            return;
        }

        $mutex = Craft::$app->getMutex();
        $lockName = GenerateCacheService::MUTEX_LOCK_NAME_CACHE_RECORDS;

        if (!$mutex->acquire($lockName, Blitz::$plugin->settings->mutexTimeout)) {
            return;
        }

        CacheRecord::deleteAll();

        $this->runGarbageCollection();

        $mutex->release($lockName);

        if ($this->hasEventHandlers(self::EVENT_AFTER_FLUSH_ALL_CACHE)) {
            $this->trigger(self::EVENT_AFTER_FLUSH_ALL_CACHE, $event);
        }
    }

    /**
     * Runs garbage collection.
     */
    public function runGarbageCollection(): void
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

    /**
     * Resets auto increment values of the given table.
     */
    private function _resetAutoIncrement(string $table): void
    {
        $db = Craft::$app->getDb();
        $dbDriver = $db->getDriverName();
        $sql = '';

        if ($dbDriver == 'mysql') {
            $sql = 'ALTER TABLE ' . $table . ' AUTO_INCREMENT = 1';
        }
        elseif ($dbDriver == 'postgres') {
            $sql = 'ALTER SEQUENCE ' . $table . '_id_seq RESTART WITH 1';
        }

        if ($sql) {
            try {
                $db->createCommand($sql)->execute();
            }
            catch (Exception $exception) {
                Blitz::$plugin->log('Failed to reset auto increment: ' . $exception->getMessage(), [], LogLevel::ERROR);
            }
        }
    }
}
