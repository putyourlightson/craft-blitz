<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\helpers;

use Craft;
use craft\db\ActiveQuery;
use craft\helpers\StringHelper;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\models\SiteUriModel;
use putyourlightson\blitz\records\DriverQueueRecord;
use putyourlightson\blitz\records\QueueSiteUriRecord;
use RuntimeException;
use yii\db\Expression;

/**
 * Keeps pending URLs outside queue payloads so overlapping jobs can share and deduplicate work.
 *
 * @since 5.16.0
 */
class DriverQueueHelper
{
    /**
     * Preserves input order and priority so deduplication does not delay urgent URLs.
     *
     * @param SiteUriModel[]|array[] $siteUris
     */
    public static function add(string $driverId, string $driverMethod, array $siteUris, ?int $priority = null): bool
    {
        if ($siteUris === []) {
            return false;
        }

        $priority ??= Blitz::$plugin->settings->driverJobPriority ?? 1024;
        $siteUrisByHash = [];
        foreach ($siteUris as $siteUri) {
            if (!is_array($siteUri)) {
                $siteUri = $siteUri->toArray();
            }
            $siteUri['siteId'] = (int)$siteUri['siteId'];
            $hash = hash('sha256', $siteUri['siteId'] . ':' . $siteUri['uri']);
            $siteUrisByHash[$hash] ??= $siteUri;
        }

        $revision = StringHelper::UUID();
        $queueTable = DriverQueueRecord::tableName();
        $db = Craft::$app->getDb();

        // Release the pending-work lock between chunks so bulk refreshes do not block saves.
        foreach (array_chunk($siteUrisByHash, 500, true) as $chunk) {
            self::withQueueTransaction(function() use ($db, $chunk, $queueTable, $priority, $revision, $driverId, $driverMethod) {
                $siteUriRowsByHash = QueueSiteUriRecord::find()
                    ->select(['id', 'uriHash'])
                    ->where(['uriHash' => array_keys($chunk)])
                    ->indexBy('uriHash')
                    ->asArray()
                    ->all();
                $newSiteUriRows = [];
                foreach ($chunk as $hash => $siteUri) {
                    if (!isset($siteUriRowsByHash[$hash])) {
                        $newSiteUriRows[] = [$siteUri['siteId'], $siteUri['uri'], $hash];
                    }
                }
                if ($newSiteUriRows !== []) {
                    $db->createCommand()->batchInsert(QueueSiteUriRecord::tableName(), ['siteId', 'uri', 'uriHash'], $newSiteUriRows)->execute();
                    $siteUriRowsByHash = QueueSiteUriRecord::find()
                        ->select(['id', 'uriHash'])
                        ->where(['uriHash' => array_keys($chunk)])
                        ->indexBy('uriHash')
                        ->asArray()
                        ->all();
                }

                $siteUriIds = [];
                foreach ($chunk as $hash => $siteUri) {
                    $siteUriIds[] = (int)$siteUriRowsByHash[$hash]['id'];
                }
                $existingSiteUriIds = DriverQueueRecord::find()
                    ->select('siteUriId')
                    ->where(['siteUriId' => $siteUriIds, 'driverId' => $driverId, 'driverMethod' => $driverMethod])
                    ->column();
                $existingSiteUriIdSet = array_fill_keys($existingSiteUriIds, true);
                if ($existingSiteUriIds !== []) {
                    // A new revision preserves requests made while a worker is processing the previous one.
                    $db->createCommand()->update($queueTable, [
                        'revision' => $revision,
                        'priority' => new Expression('LEAST([[priority]], :priority)', [':priority' => $priority]),
                    ], ['siteUriId' => $existingSiteUriIds, 'driverId' => $driverId, 'driverMethod' => $driverMethod], [], false)->execute();
                }
                $newQueueRows = [];
                foreach ($siteUriIds as $siteUriId) {
                    if (!isset($existingSiteUriIdSet[$siteUriId])) {
                        $newQueueRows[] = [$siteUriId, $driverId, $driverMethod, $priority, $revision];
                    }
                }
                if ($newQueueRows !== []) {
                    $db->createCommand()->batchInsert($queueTable, ['siteUriId', 'driverId', 'driverMethod', 'priority', 'revision'], $newQueueRows)->execute();
                }
            });
        }

        return true;
    }

    /**
     * Keeps work pending until the callback succeeds so failures can be retried.
     *
     * @param callable(SiteUriModel[]): void $callback
     */
    public static function process(string $driverId, string $driverMethod, callable $callback, ?int $limit = null): int
    {
        return self::withLock(substr(hash('sha256', $driverId . "\0" . $driverMethod), 0, 32), function() use ($driverId, $driverMethod, $callback, $limit) {
            $query = self::getPendingQuery($driverId, $driverMethod, true)
                ->select(['work.id', 'work.siteUriId', 'work.revision', 'uri.siteId', 'uri.uri'])
                ->innerJoin(['uri' => QueueSiteUriRecord::tableName()], '[[uri.id]] = [[work.siteUriId]]')
                ->orderBy(['work.priority' => SORT_ASC, 'work.id' => SORT_ASC])
                ->limit($limit);
            $queueRows = $query->all();
            if ($queueRows === []) {
                return 0;
            }

            $callback(array_map(fn(array $queueRow) => new SiteUriModel(['siteId' => (int)$queueRow['siteId'], 'uri' => $queueRow['uri']]), $queueRows));

            foreach (array_chunk($queueRows, 500) as $chunk) {
                self::withQueueTransaction(function() use ($chunk) {
                    $condition = ['or'];
                    foreach ($chunk as $queueRow) {
                        $condition[] = ['id' => $queueRow['id'], 'revision' => $queueRow['revision']];
                    }
                    Craft::$app->getDb()->createCommand()->delete(DriverQueueRecord::tableName(), $condition)->execute();
                    self::deleteUnusedSiteUris(array_column($chunk, 'siteUriId'));
                });
            }

            return count($queueRows);
        });
    }

    /**
     * Includes blocked work so waiting operations retain their queue priority.
     */
    public static function getPendingPriority(string $driverId, string $driverMethod): ?int
    {
        $priority = self::getPendingQuery($driverId, $driverMethod, false)->min('work.priority');

        return $priority === null || $priority === false ? null : (int)$priority;
    }

    /**
     * Includes blocked and executing work so job descriptions reflect all outstanding URLs.
     */
    public static function getPendingCount(string $driverId, string $driverMethod): int
    {
        return (int)DriverQueueRecord::find()->where(['driverId' => $driverId, 'driverMethod' => $driverMethod])->count();
    }

    /**
     * Removes work alongside released jobs so later jobs do not process cancelled requests.
     */
    public static function clear(string $driverId, ?string $driverMethod = null): void
    {
        self::withQueueTransaction(function() use ($driverId, $driverMethod) {
            $condition = ['driverId' => $driverId];
            if ($driverMethod !== null) {
                $condition['driverMethod'] = $driverMethod;
            }
            Craft::$app->getDb()->createCommand()->delete(DriverQueueRecord::tableName(), $condition)->execute();
            self::deleteUnusedSiteUris();
        });
    }

    /**
     * Applies prerequisites only when selecting executable work so counts and priorities
     * remain available while operations are blocked.
     */
    private static function getPendingQuery(string $driverId, string $driverMethod, bool $readyOnly): ActiveQuery
    {
        $query = DriverQueueRecord::find()
            ->alias('work')
            ->where(['work.driverId' => $driverId, 'work.driverMethod' => $driverMethod])
            ->asArray();
        if (!$readyOnly) {
            return $query;
        }

        $dependencies = match ([$driverId, $driverMethod]) {
            [DeployerHelper::DRIVER_ID, 'deployUrisWithProgress'] => [CacheGeneratorHelper::DRIVER_ID => 'generateUrisWithProgress'],
            [CachePurgerHelper::DRIVER_ID, 'purgeUrisWithProgress'] => Blitz::$plugin->settings->shouldClearOnRefresh() ? [] : [
                CacheGeneratorHelper::DRIVER_ID => 'generateUrisWithProgress',
                DeployerHelper::DRIVER_ID => 'deployUrisWithProgress',
            ],
            default => [],
        };
        foreach ($dependencies as $dependencyDriverId => $dependencyMethod) {
            // Wait for the whole preceding operation so deployments remain batched and SSI parents purge last.
            $dependencyQuery = DriverQueueRecord::find()
                ->select(new Expression('1'))
                ->where(['driverId' => $dependencyDriverId, 'driverMethod' => $dependencyMethod]);
            $query->andWhere(['not exists', $dependencyQuery]);
        }

        return $query;
    }

    /**
     * Removes unreferenced identities so completed URLs do not accumulate indefinitely.
     *
     * @param int[]|null $siteUriIds
     */
    private static function deleteUnusedSiteUris(?array $siteUriIds = null): void
    {
        $condition = ['and'];
        if ($siteUriIds !== null) {
            $condition[] = ['id' => $siteUriIds];
        }
        $condition[] = [
            'not exists',
            DriverQueueRecord::find()
                ->select(new Expression('1'))
                ->where('[[siteUriId]] = ' . QueueSiteUriRecord::tableName() . '.[[id]]'),
        ];
        Craft::$app->getDb()->createCommand()->delete(QueueSiteUriRecord::tableName(), $condition)->execute();
    }

    /**
     * Serialises changes with cleanup so new work cannot lose its URI identity.
     */
    private static function withQueueTransaction(callable $callback): mixed
    {
        return self::withLock('pending', fn() => Craft::$app->getDb()->transaction($callback));
    }

    /**
     * Releases locks after failures so later jobs can retry.
     */
    private static function withLock(string $name, callable $callback): mixed
    {
        $mutex = Craft::$app->getMutex();
        $lock = 'blitz-driver-queue-' . $name;
        if (!$mutex->acquire($lock, Blitz::$plugin->settings->mutexTimeout)) {
            throw new RuntimeException('Unable to lock pending Blitz ' . $name . ' work.');
        }
        try {
            return $callback();
        } finally {
            $mutex->release($lock);
        }
    }
}
