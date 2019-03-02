<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\helpers;

use craft\errors\MissingComponentException;
use craft\events\RegisterComponentTypesEvent;
use craft\helpers\Component;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\drivers\purgers\BaseCachePurger;
use putyourlightson\blitz\drivers\purgers\DummyPurger;
use putyourlightson\blitz\drivers\purgers\CachePurgerInterface;
use yii\base\Event;
use yii\base\InvalidConfigException;

class CachePurgerHelper
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
     * Returns all purger drivers.
     *
     * @return BaseCachePurger[]
     */
    public static function getAllDrivers(): array
    {
        $drivers = [];

        $purgerTypes = [
            DummyPurger::class,
        ];

        $purgerTypes = array_unique(array_merge(
            $purgerTypes,
            Blitz::$plugin->settings->extraCachePurgerTypes
        ), SORT_REGULAR);

        $event = new RegisterComponentTypesEvent([
            'types' => $purgerTypes,
        ]);
        Event::trigger(static::class, self::EVENT_REGISTER_PURGER_TYPES, $event);

        $purgerTypes = $event->types;

        /** @var BaseCachePurger $class */
        foreach ($purgerTypes as $class) {
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
     * Creates a purger driver of the provided type with the optional settings.
     *
     * @param string $type
     * @param array|null $settings
     *
     * @return CachePurgerInterface|null
     */
    public static function createDriver(string $type, array $settings = null)
    {
        $driver = null;

        try {
            /** @var CachePurgerInterface $driver */
            $driver = Component::createComponent([
                'type' => $type,
                'settings' => $settings ?? [],
            ], CachePurgerInterface::class);
        }
        catch (InvalidConfigException $e) {}
        catch (MissingComponentException $e) {}

        return $driver;
    }
}