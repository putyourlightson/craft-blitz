<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\helpers;

use Craft;
use craft\base\SavableComponentInterface;
use craft\errors\MissingComponentException;
use craft\helpers\Component;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\jobs\DriverJob;
use putyourlightson\blitz\models\SiteUriModel;
use yii\base\InvalidConfigException;

class BaseDriverHelper
{
    // Static
    // =========================================================================

    /**
     * Creates drivers of the provided types.
     *
     * @param array $types
     *
     * @return SavableComponentInterface[]
     */
    public static function createDrivers(array $types): array
    {
        $drivers = [];

        /** @var SavableComponentInterface $type */
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
     * @param array|null $settings
     *
     * @return SavableComponentInterface|null
     */
    public static function createDriver(string $type, array $settings = [])
    {
        $driver = null;

        try {
            $driver = Component::createComponent([
                'type' => $type,
                'settings' => $settings,
            ]);
        }
        catch (InvalidConfigException $e) {}
        catch (MissingComponentException $e) {}

        return $driver;
    }

    /**
     * Adds a driver job to the queue.
     *
     * @param SiteUriModel[] $siteUris
     * @param callable $jobHandler
     * @param string|null $description
     * @param int|null $delay
     */
    public static function addDriverJob(array $siteUris, callable $jobHandler, string $description = null, int $delay = null)
    {
        // Convert SiteUriModels to arrays to keep the job data from getting too big
        foreach ($siteUris as &$siteUri) {
            if ($siteUri instanceof SiteUriModel) {
                $siteUri = $siteUri->toArray();
            }
        }

        // Add job to queue with a priority and delay
        Craft::$app->getQueue()
            ->priority(Blitz::$plugin->settings->driverJobPriority)
            ->delay($delay)
            ->push(new DriverJob([
                'siteUris' => $siteUris,
                'jobHandler' => $jobHandler,
                'description' => $description,
            ]));
    }
}
