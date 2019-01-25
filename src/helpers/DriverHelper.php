<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\helpers;

use craft\errors\MissingComponentException;
use craft\events\RegisterComponentTypesEvent;
use craft\helpers\Component;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\drivers\storage\BaseCacheStorage;
use putyourlightson\blitz\drivers\storage\CacheStorageInterface;
use putyourlightson\blitz\drivers\storage\FileStorage;
use putyourlightson\blitz\drivers\storage\YiiCacheStorage;
use yii\base\Event;
use yii\base\InvalidConfigException;

class DriverHelper
{
    // Constants
    // =========================================================================

    /**
     * @event RegisterComponentTypesEvent
     */
    const EVENT_REGISTER_DRIVER_TYPES = 'registerDriverTypes';

    // Static
    // =========================================================================

    /**
     * Returns all driver types.
     *
     * @return string[]
     */
    public static function getAllDriverTypes(): array
    {
        $driverTypes = [
            FileStorage::class,
            YiiCacheStorage::class,
        ];

        $driverTypes = array_unique(array_merge(
            $driverTypes,
            Blitz::$plugin->settings->driverTypes
        ), SORT_REGULAR);

        $event = new RegisterComponentTypesEvent([
            'types' => $driverTypes,
        ]);
        Event::trigger(static::class, self::EVENT_REGISTER_DRIVER_TYPES, $event);

        return $event->types;
    }

    /**
     * Returns all drivers.
     *
     * @return BaseCacheStorage[]
     */
    public static function getAllDrivers(): array
    {
        $drivers = [];

        /** @var BaseCacheStorage $class */
        foreach (DriverHelper::getAllDriverTypes() as $class) {
            if ($class::isSelectable()) {
                $driver = self::createDriver($class);

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
     * @return CacheStorageInterface|null
     */
    public static function createDriver(string $type, array $settings = null)
    {
        $driver = null;

        try {
            /** @var CacheStorageInterface $driver */
            $driver = Component::createComponent([
                'type' => $type,
                'settings' => $settings ?? [],
            ], CacheStorageInterface::class);
        }
        catch (InvalidConfigException $e) {}
        catch (MissingComponentException $e) {}

        return $driver;
    }
}