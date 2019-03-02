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

class CacheStorageHelper
{
    // Constants
    // =========================================================================

    /**
     * @event RegisterComponentTypesEvent
     */
    const EVENT_REGISTER_STORAGE_TYPES = 'registerStorageTypes';

    // Static
    // =========================================================================

    /**
     * Returns all storage types.
     *
     * @return string[]
     */
    public static function getAllTypes(): array
    {
        $storageTypes = [
            FileStorage::class,
            YiiCacheStorage::class,
        ];

        $storageTypes = array_unique(array_merge(
            $storageTypes,
            Blitz::$plugin->settings->extraCacheStorageTypes
        ), SORT_REGULAR);

        $event = new RegisterComponentTypesEvent([
            'types' => $storageTypes,
        ]);
        Event::trigger(static::class, self::EVENT_REGISTER_STORAGE_TYPES, $event);

        return $event->types;
    }

    /**
     * Returns all storage drivers.
     *
     * @return BaseCacheStorage[]
     */
    public static function getAllDrivers(): array
    {
        $drivers = [];

        /** @var BaseCacheStorage $class */
        foreach (CacheStorageHelper::getAllTypes() as $class) {
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
     * Creates a storage driver of the provided type with the optional settings.
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