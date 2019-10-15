<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\helpers;

use craft\events\RegisterComponentTypesEvent;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\drivers\storage\BaseCacheStorage;
use putyourlightson\blitz\drivers\storage\FileStorage;
use putyourlightson\blitz\drivers\storage\YiiCacheStorage;
use yii\base\Event;

class CacheStorageHelper extends BaseDriverHelper
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
            Blitz::$plugin->settings->cacheStorageTypes
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
        return self::createDrivers(self::getAllTypes());
    }
}
