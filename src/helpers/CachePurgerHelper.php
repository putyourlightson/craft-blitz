<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\helpers;

use Craft;
use craft\base\SavableComponent;
use craft\events\RegisterComponentTypesEvent;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\drivers\purgers\CloudflarePurger;
use putyourlightson\blitz\drivers\purgers\DummyPurger;
use putyourlightson\blitz\models\SiteUriModel;
use yii\base\Event;

class CachePurgerHelper extends BaseDriverHelper
{
    /**
     * @event RegisterComponentTypesEvent
     */
    public const EVENT_REGISTER_PURGER_TYPES = 'registerPurgerTypes';

    /**
     * Returns all purger types.
     *
     * @return string[]
     */
    public static function getAllTypes(): array
    {
        $purgerTypes = [
            DummyPurger::class,
            CloudflarePurger::class,
        ];

        $purgerTypes = array_unique(array_merge(
            $purgerTypes,
            Blitz::$plugin->settings->cachePurgerTypes
        ), SORT_REGULAR);

        $event = new RegisterComponentTypesEvent([
            'types' => $purgerTypes,
        ]);
        Event::trigger(static::class, self::EVENT_REGISTER_PURGER_TYPES, $event);

        return $event->types;
    }

    /**
     * Returns all purger drivers.
     *
     * @return SavableComponent[]
     */
    public static function getAllDrivers(): array
    {
        return self::createDrivers(self::getAllTypes());
    }

    /**
     * Adds a purger job to the queue.
     *
     * @param SiteUriModel[] $siteUris
     */
    public static function addPurgerJob(array $siteUris, string $driverMethod, int $priority = null): void
    {
        $description = Craft::t('blitz', 'Purging pages');

        self::addDriverJob($siteUris, 'cachePurger', $driverMethod, $description, $priority);
    }
}
