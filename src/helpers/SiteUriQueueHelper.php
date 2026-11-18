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
class SiteUriQueueHelper
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
        $urisByHash = [];
        foreach ($siteUris as $siteUri) {
            if (!is_array($siteUri)) {
                $siteUri = $siteUri->toArray();
            }
            $siteUri['siteId'] = (int)$siteUri['siteId'];
            $hash = hash('sha256', $siteUri['siteId'] . ':' . $siteUri['uri']);
            $urisByHash[$hash] ??= $siteUri;
        }

        $revision = StringHelper::UUID();
        $table = DriverQueueRecord::tableName();
        $db = Craft::$app->getDb();

        // Release the pending-work lock between chunks so bulk refreshes do not block saves.
        foreach (array_chunk($urisByHash, 500, true) as $chunk) {
            self::updatePending(function() use ($db, $chunk, $table, $priority, $revision, $driverId, $driverMethod) {
                $idsByHash = QueueSiteUriRecord::find()
                    ->select(['id', 'uriHash'])
                    ->where(['uriHash' => array_keys($chunk)])
                    ->indexBy('uriHash')
                    ->asArray()
                    ->all();
                $newUris = [];
                foreach ($chunk as $hash => $siteUri) {
                    if (!isset($idsByHash[$hash])) {
                        $newUris[] = [$siteUri['siteId'], $siteUri['uri'], $hash];
                    }
                }
                if ($newUris !== []) {
                    $db->createCommand()->batchInsert(QueueSiteUriRecord::tableName(), ['siteId', 'uri', 'uriHash'], $newUris)->execute();
                    $idsByHash = QueueSiteUriRecord::find()
                        ->select(['id', 'uriHash'])
                        ->where(['uriHash' => array_keys($chunk)])
                        ->indexBy('uriHash')
                        ->asArray()
                        ->all();
                }

                $ids = [];
                foreach ($chunk as $hash => $siteUri) {
                    $ids[] = (int)$idsByHash[$hash]['id'];
                }
                $existingIds = DriverQueueRecord::find()
                    ->select('siteUriId')
                    ->where(['siteUriId' => $ids, 'driverId' => $driverId, 'driverMethod' => $driverMethod])
                    ->column();
                $existingSet = array_fill_keys($existingIds, true);
                if ($existingIds !== []) {
                    // A new revision preserves requests made while a worker is processing the previous one.
                    $db->createCommand()->update($table, [
                        'revision' => $revision,
                        'priority' => new Expression('LEAST([[priority]], :priority)', [':priority' => $priority]),
                    ], ['siteUriId' => $existingIds, 'driverId' => $driverId, 'driverMethod' => $driverMethod], [], false)->execute();
                }
                $newWork = [];
                foreach ($ids as $id) {
                    if (!isset($existingSet[$id])) {
                        $newWork[] = [$id, $driverId, $driverMethod, $priority, $revision];
                    }
                }
                if ($newWork !== []) {
                    $db->createCommand()->batchInsert($table, ['siteUriId', 'driverId', 'driverMethod', 'priority', 'revision'], $newWork)->execute();
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
            $query = self::query($driverId, $driverMethod, true)
                ->select(['work.id', 'work.siteUriId', 'work.revision', 'uri.siteId', 'uri.uri'])
                ->innerJoin(['uri' => QueueSiteUriRecord::tableName()], '[[uri.id]] = [[work.siteUriId]]')
                ->orderBy(['work.priority' => SORT_ASC, 'work.id' => SORT_ASC])
                ->limit($limit);
            $rows = $query->all();
            if ($rows === []) {
                return 0;
            }

            $callback(array_map(fn(array $row) => new SiteUriModel(['siteId' => (int)$row['siteId'], 'uri' => $row['uri']]), $rows));

            foreach (array_chunk($rows, 500) as $chunk) {
                self::updatePending(function() use ($chunk) {
                    $condition = ['or'];
                    foreach ($chunk as $row) {
                        $condition[] = ['id' => $row['id'], 'revision' => $row['revision']];
                    }
                    Craft::$app->getDb()->createCommand()->delete(DriverQueueRecord::tableName(), $condition)->execute();
                    self::deleteUnusedSiteUris(array_column($chunk, 'siteUriId'));
                });
            }

            return count($rows);
        });
    }

    /**
     * Includes blocked work so waiting operations retain their queue priority.
     */
    public static function getPriority(string $driverId, string $driverMethod): ?int
    {
        $priority = self::query($driverId, $driverMethod, false)->min('work.priority');

        return $priority === null || $priority === false ? null : (int)$priority;
    }

    /**
     * Includes blocked and executing work so job descriptions reflect all outstanding URLs.
     */
    public static function getCount(string $driverId, string $driverMethod): int
    {
        return (int)DriverQueueRecord::find()->where(['driverId' => $driverId, 'driverMethod' => $driverMethod])->count();
    }

    /**
     * Removes work alongside released jobs so later jobs do not process cancelled requests.
     */
    public static function clear(string $driverId, ?string $driverMethod = null): void
    {
        self::updatePending(function() use ($driverId, $driverMethod) {
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
    private static function query(string $driverId, string $driverMethod, bool $ready): ActiveQuery
    {
        $query = DriverQueueRecord::find()
            ->alias('work')
            ->where(['work.driverId' => $driverId, 'work.driverMethod' => $driverMethod])
            ->asArray();
        if (!$ready) {
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
        foreach ($dependencies as $id => $method) {
            // Wait for the whole preceding operation so deployments remain batched and SSI parents purge last.
            $pending = DriverQueueRecord::find()
                ->select(new Expression('1'))
                ->where(['driverId' => $id, 'driverMethod' => $method]);
            $query->andWhere(['not exists', $pending]);
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
    private static function updatePending(callable $callback): mixed
    {
        return self::withLock('pending', fn() => Craft::$app->getDb()->transaction($callback));
    }

    /**
     * Releases locks after failures so later jobs can retry.
     */
    private static function withLock(string $name, callable $callback): mixed
    {
        $mutex = Craft::$app->getMutex();
        $lock = 'blitz-site-uri-queue-' . $name;
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
