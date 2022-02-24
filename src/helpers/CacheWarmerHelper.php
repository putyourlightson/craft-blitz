<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\helpers;

use Craft;
use craft\base\SavableComponent;
use craft\events\RegisterComponentTypesEvent;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\drivers\warmers\DummyWarmer;
use putyourlightson\blitz\drivers\warmers\GuzzleWarmer;
use putyourlightson\blitz\drivers\warmers\LocalWarmer;
use putyourlightson\blitz\models\SiteUriModel;
use yii\base\Event;

class CacheWarmerHelper extends BaseDriverHelper
{
    /**
     * @event RegisterComponentTypesEvent
     */
    public const EVENT_REGISTER_WARMER_TYPES = 'registerWarmerTypes';

    /**
     * Returns all warmer types.
     *
     * @return string[]
     */
    public static function getAllTypes(): array
    {
        $warmerTypes = [
            DummyWarmer::class,
            GuzzleWarmer::class,
            LocalWarmer::class,
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
     * @return SavableComponent[]
     */
    public static function getAllDrivers(): array
    {
        return self::createDrivers(self::getAllTypes());
    }

    /**
     * Adds a warmer job to the queue.
     */
    public static function addWarmerJob(array $siteUris, string $driverMethod, int $delay = null, int $priority = null)
    {
        $description = Craft::t('blitz', 'Warming Blitz cache');

        self::addDriverJob($siteUris, 'cacheWarmer', $driverMethod, $description, $delay, $priority);
    }
}
