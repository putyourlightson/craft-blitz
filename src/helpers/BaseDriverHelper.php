<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\helpers;

use craft\base\SavableComponent;
use craft\helpers\Component;
use craft\helpers\Queue;
use craft\queue\Queue as CraftQueue;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\jobs\DriverJob;
use putyourlightson\blitz\jobs\GenerateCacheJob;
use putyourlightson\blitz\jobs\PurgeSiteJob;
use yii\base\InvalidArgumentException;

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
        $queue = Blitz::$plugin->queue;

        if (!$queue instanceof CraftQueue) {
            return;
        }

        foreach ($queue->getJobInfo() as $info) {
            if ($info['status'] === CraftQueue::STATUS_RESERVED) {
                continue;
            }

            try {
                $details = $queue->getJobDetails($info['id']);
            } catch (InvalidArgumentException) {
                // A worker may have completed the job since the list was fetched.
                continue;
            }

            if ($details['status'] === CraftQueue::STATUS_RESERVED) {
                continue;
            }

            $job = $details['job'];
            $matches = match (true) {
                $job instanceof GenerateCacheJob => $driverId === CacheGeneratorHelper::DRIVER_ID,
                $job instanceof PurgeSiteJob => $driverId === CachePurgerHelper::DRIVER_ID,
                $job instanceof DriverJob => $job->driverId === $driverId,
                default => false,
            };

            if ($matches) {
                $queue->release($info['id']);
            }
        }
    }
}
