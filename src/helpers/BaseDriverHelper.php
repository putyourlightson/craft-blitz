<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\helpers;

use Craft;
use craft\base\SavableComponent;
use craft\helpers\Component;
use craft\queue\Queue;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\jobs\DriverJob;
use putyourlightson\blitz\models\SiteUriModel;

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
        /** @noinspection PhpUnnecessaryLocalVariableInspection */
        $driver = Component::createComponent([
            'type' => $type,
            'settings' => $settings,
        ],                                   SavableComponent::class);

        return $driver;
    }

    /**
     * Adds a driver job to the queue.
     *
     * @param SiteUriModel[] $siteUris
     */
    public static function addDriverJob(array $siteUris, string $driverId, string $driverMethod, string $description = null, int $priority = null)
    {
        $priority = $priority ?? Blitz::$plugin->settings->driverJobPriority;

        // Convert SiteUriModels to arrays to keep the job data minimal
        foreach ($siteUris as &$siteUri) {
            if ($siteUri instanceof SiteUriModel) {
                $siteUri = $siteUri->toArray();
            }
        }

        $job = new DriverJob([
            'siteUris' => $siteUris,
            'driverId' => $driverId,
            'driverMethod' => $driverMethod,
            'description' => $description,
        ]);

        // Add job to queue with a priority
        /** @var Queue $queue */
        $queue = Craft::$app->getQueue();

        // Some queues don't support custom push priorities.
        if (method_exists($queue, 'priority')) {
            $queue->priority($priority)->push($job);
        }
        else {
            $queue->push($job);
        }
    }
}
