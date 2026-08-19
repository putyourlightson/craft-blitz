<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\helpers;

use Craft;
use craft\base\SavableComponent;
use craft\db\Query;
use craft\helpers\Component;
use craft\helpers\Queue;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\jobs\DriverJob;

class BaseDriverHelper
{
    /**
     * Creates drivers of the provided types.
     *
     * @return SavableComponent[]
     */
    public static function createDrivers(array $types): array
    {
        $drivers = [];

        foreach ($types as $type) {
            if ($type::isSelectable()) {
                $drivers[] = self::createDriver($type);
            }
        }

        return $drivers;
    }

    /**
     * Creates a driver of the provided type with the optional settings.
     */
    public static function createDriver(string $type, array $settings = []): SavableComponent
    {
        /** @var SavableComponent $driver */
        $driver = Component::createComponent([
            'type' => $type,
            'settings' => $settings,
        ], SavableComponent::class);

        return $driver;
    }

    /**
     * Adds a driver job to the queue.
     */
    public static function addDriverJob(array $siteUris, string $driverId, string $driverMethod, string $description = null, int $priority = null): void
    {
        $siteUris = SiteUriHelper::getSiteUrisFlattenedToArrays($siteUris);
        $priority = $priority ?? Blitz::$plugin->settings->driverJobPriority;

        $job = new DriverJob([
            'siteUris' => $siteUris,
            'driverId' => $driverId,
            'driverMethod' => $driverMethod,
            'description' => $description,
        ]);
        Queue::push(
            job: $job,
            priority: $priority,
            queue: Blitz::$plugin->queue,
        );
    }

    /**
     * Releases driver jobs from the queue.
     */
    public static function releaseDriverJobs(string $driverId): void
    {
        $queue = Craft::$app->getQueue();

        // Only release jobs if the queue is a Craft queue.
        if (!($queue instanceof \craft\queue\Queue)) {
            return;
        }

        $query = (new Query())
            ->from([$queue->tableName])
            ->select(['id'])
            ->where(['dateReserved' => null]);

        if ($driverId === 'cacheGenerator') {
            $query->andWhere(['like', 'job', '"putyourlightson\\blitz\\jobs\\GenerateCacheJob"']);
        } else {
            $query->andWhere(['like', 'job', '"putyourlightson\\blitz\\jobs\\DriverJob"']);
            $query->andWhere(['like', 'job', '"' . $driverId . '"']);
        }

        $jobIds = $query->column();

        foreach ($jobIds as $jobId) {
            $queue->release($jobId);
        }
    }
}
