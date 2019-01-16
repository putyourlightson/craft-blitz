<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\helpers;

use craft\errors\MissingComponentException;
use craft\events\RegisterComponentTypesEvent;
use craft\helpers\Component;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\drivers\DriverInterface;
use putyourlightson\blitz\drivers\FileDriver;
use yii\base\Event;
use yii\base\InvalidConfigException;

class DriverHelper
{
    // Constants
    // =========================================================================

    /**
     * @event RefreshCacheEvent
     */
    const EVENT_REGISTER_DRIVER_TYPES = 'registerDriverTypes';

    // Static Methods
    // =========================================================================

    /**
     * Returns all driver types.
     *
     * @return string[]
     */
    public static function getAllDriverTypes(): array
    {
        $drivers = [
            FileDriver::class,
        ];

        $drivers = array_unique(array_merge(
            $drivers,
            Blitz::$plugin->getSettings()->driverTypes ?? []
        ), SORT_REGULAR);

        $event = new RegisterComponentTypesEvent([
            'types' => $drivers,
        ]);
        Event::trigger(static::class, self::EVENT_REGISTER_DRIVER_TYPES, $event);

        return $event->types;
    }

    /**
     * Creates a driver of the provided type with the optional settings.
     *
     * @param string $type
     * @param array|null $settings
     *
     * @return DriverInterface
     * @throws MissingComponentException
     * @throws InvalidConfigException
     */
    public static function createDriver(string $type, array $settings = null): DriverInterface
    {
        /** @var DriverInterface $driver */
        $driver = Component::createComponent([
            'type' => $type,
            'settings' => $settings ?? [],
        ], DriverInterface::class);

        return $driver;
    }
}