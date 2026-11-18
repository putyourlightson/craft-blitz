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
        $priority = $priority ?? Blitz::$plugin->settings->driverJobPriority;
        if (!SiteUriQueueHelper::add($driverId, $driverMethod, $siteUris, $priority)) {
            return;
        }

        $job = new DriverJob([
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
     * Shared jobs become no-ops so cancellation cannot remove jobs for newly added work.
     */
    public static function releaseDriverJobs(string $driverId): void
    {
        SiteUriQueueHelper::clear($driverId);

        $queue = Blitz::$plugin->queue;

        if ($driverId !== CachePurgerHelper::DRIVER_ID || !($queue instanceof CraftQueue)) {
            return;
        }

        // Site purges carry their own work, so clearing pending URLs cannot cancel them.
        foreach ($queue->getJobInfo() as $info) {
            if ($info['status'] === CraftQueue::STATUS_RESERVED) {
                continue;
            }
            try {
                $details = $queue->getJobDetails($info['id']);
            } catch (InvalidArgumentException) {
                // Another worker may have completed the job since the list was fetched.
                continue;
            }
            if ($details['status'] !== CraftQueue::STATUS_RESERVED && $details['job'] instanceof PurgeSiteJob) {
                $queue->release($info['id']);
            }
        }
    }
}
