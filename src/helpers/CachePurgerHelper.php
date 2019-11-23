<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\helpers;

use craft\base\SavableComponent;
use craft\events\RegisterComponentTypesEvent;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\drivers\purgers\DummyPurger;
use yii\base\Event;

class CachePurgerHelper extends BaseDriverHelper
{
    // Constants
    // =========================================================================

    /**
     * @event RegisterComponentTypesEvent
     */
    const EVENT_REGISTER_PURGER_TYPES = 'registerPurgerTypes';

    // Static
    // =========================================================================

    /**
     * Returns all purger types.
     *
     * @return string[]
     */
    public static function getAllTypes(): array
    {
        $purgerTypes = [
            DummyPurger::class,
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
}
