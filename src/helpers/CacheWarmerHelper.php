<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\helpers;

use craft\events\RegisterComponentTypesEvent;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\drivers\warmers\BaseCacheWarmer;
use putyourlightson\blitz\drivers\warmers\DummyWarmer;
use putyourlightson\blitz\drivers\warmers\DefaultWarmer;
use putyourlightson\blitz\drivers\warmers\StaticSiteGenerator;
use yii\base\Event;

class CacheWarmerHelper
{
    // Constants
    // =========================================================================

    /**
     * @event RegisterComponentTypesEvent
     */
    const EVENT_REGISTER_WARMER_TYPES = 'registerWarmerTypes';

    // Static
    // =========================================================================

    /**
     * Returns all warmer types.
     *
     * @return string[]
     */
    public static function getAllTypes(): array
    {
        $warmerTypes = [
            DefaultWarmer::class,
            StaticSiteGenerator::class,
        ];

        $warmerTypes = array_unique(array_merge(
            $warmerTypes,
            Blitz::$plugin->settings->cacheWarmerTypes
        ), SORT_REGULAR);

        $event = new RegisterComponentTypesEvent([
            'types' => $warmerTypes,
        ]);
        Event::trigger(static::class, self::EVENT_REGISTER_WARMER_TYPES, $event);

        return $event->types;
    }

    /**
     * Returns all warmer drivers.
     *
     * @return BaseCacheWarmer[]
     */
    public static function getAllDrivers(): array
    {
        return CacheDriverHelper::createDrivers(self::getAllTypes());
    }
}
