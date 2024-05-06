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
use putyourlightson\blitz\records\IncludeRecord;
use yii\db\Exception;
use yii\log\Logger;

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
    public function flushAll(bool $isAfterFullClear = false): void
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

        $this->deleteAllCacheRecords();
        $this->runGarbageCollection($isAfterFullClear);
        $mutex->release($lockName);

        if ($this->hasEventHandlers(self::EVENT_AFTER_FLUSH_ALL_CACHE)) {
            $this->trigger(self::EVENT_AFTER_FLUSH_ALL_CACHE, $event);
        }
    }

    /**
     * Runs garbage collection.
     */
    public function runGarbageCollection(bool $isAfterFullClear = false): void
    {
        // Get and delete element query records without an associated element query cache
        $elementQueryRecordIds = ElementQueryRecord::find()
            ->select(['id'])
            ->joinWith('elementQueryCaches')
            ->where(['cacheId' => null])
            ->column();

        ElementQueryRecord::deleteAll(['id' => $elementQueryRecordIds]);

        // Delete includes only after a cache clear
        if ($isAfterFullClear === true) {
            IncludeRecord::deleteAll();
        }

        // Check if auto increment values should be reset
        if (CacheRecord::find()->count() == 0) {
            $this->resetAutoIncrement(CacheRecord::tableName());
        }
        if (ElementQueryRecord::find()->count() == 0) {
            $this->resetAutoIncrement(ElementQueryRecord::tableName());
        }
        if (IncludeRecord::find()->count() == 0) {
            $this->resetAutoIncrement(IncludeRecord::tableName());
        }
    }

    /**
     * Resets auto increment values of the given table.
     */
    private function resetAutoIncrement(string $table): void
    {
        $db = Craft::$app->getDb();
        $dbDriver = $db->getDriverName();
        $sql = '';

        if ($dbDriver == 'mysql') {
            $sql = 'ALTER TABLE ' . $table . ' AUTO_INCREMENT=1';
        } elseif ($dbDriver == 'postgres') {
            $sql = 'ALTER SEQUENCE ' . $table . '_id_seq RESTART WITH 1';
        }

        if ($sql) {
            try {
                $db->createCommand($sql)->execute();
            } catch (Exception $exception) {
                Blitz::$plugin->log('Failed to reset auto increment: ' . $exception->getMessage(), [], Logger::LEVEL_ERROR);
            }
        }
    }

    /**
     * Deletes the cache records in batches, to avoid database memory issues.
     *
     * The reason this is important is due to foreign keys and transactions. Deleting cache records causes a cascade of deletes to other Blitz tables, potentially resulting in huge numbers of rows being deleted. Because it’s the result of a single query, it’s wrapped in a single DB transaction, so the database attempts to keep a rollback checkpoint for the whole thing (resulting in a copy of every deleted row in memory so that it can roll back if the transaction fails). If this uses more than the total memory allocated to the database then it may roll back and restart.
     *
     * @since 4.16.4
     */
    private function deleteAllCacheRecords(): void
    {
        $batchSize = 10000;
        $totalCount = CacheRecord::find()->count();
        $maxIterations = ceil($totalCount / $batchSize);

        $sql = 'DELETE FROM ' . CacheRecord::tableName() . ' LIMIT ' . $batchSize;

        // Postgres does not support LIMIT in DELETE queries.
        if (Craft::$app->getDb()->getIsPgsql()) {
            $sql = 'DELETE FROM ' . CacheRecord::tableName() . ' WHERE id IN (SELECT id FROM ' . CacheRecord::tableName() . ' LIMIT ' . $batchSize . ')';
        }

        for ($i = 0; $i < $maxIterations; $i++) {
            $deleteCount = Craft::$app->db->createCommand($sql)->execute();

            if ($deleteCount === 0) {
                return;
            }
        }
    }
}
