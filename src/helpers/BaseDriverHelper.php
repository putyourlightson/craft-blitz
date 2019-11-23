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
    // Static
    // =========================================================================

    /**
     * Creates drivers of the provided types.
     *
     * @param array $types
     *
     * @return SavableComponent[]
     */
    public static function createDrivers(array $types): array
    {
        $drivers = [];

        foreach ($types as $type) {
            if ($type::isSelectable()) {
                $driver = self::createDriver($type);

                if ($driver !== null) {
                    $drivers[] = $driver;
                }
            }
        }

        return $drivers;
    }

    /**
     * Creates a driver of the provided type with the optional settings.
     *
     * @param string $type
     * @param array $settings
     *
     * @return SavableComponent
     */
    public static function createDriver(string $type, array $settings = [])
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
     *
     * @param SiteUriModel[] $siteUris
     * @param string $driverId
     * @param string $driverMethod
     * @param string|null $description
     * @param int|null $delay
     * @param int|null $priority
     */
    public static function addDriverJob(array $siteUris, string $driverId, string $driverMethod, string $description = null, int $delay = null, int $priority = null)
    {
        $priority = $priority ?? Blitz::$plugin->settings->driverJobPriority;

        // Convert SiteUriModels to arrays to keep the job data minimal
        foreach ($siteUris as &$siteUri) {
            if ($siteUri instanceof SiteUriModel) {
                $siteUri = $siteUri->toArray();
            }
        }

        // Add job to queue with a priority and delay
        /** @var Queue $queue */
        $queue = Craft::$app->getQueue();
        $queue->priority($priority)
            ->delay($delay)
            ->push(new DriverJob([
                'siteUris' => $siteUris,
                'driverId' => $driverId,
                'driverMethod' => $driverMethod,
                'description' => $description,
            ]));
    }
}
