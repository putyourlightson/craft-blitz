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
use putyourlightson\blitz\drivers\purgers\CloudflarePurger;
use putyourlightson\blitz\drivers\purgers\DummyPurger;
use putyourlightson\blitz\drivers\purgers\CachePurgerInterface;
use yii\base\Event;
use yii\base\InvalidConfigException;

class PurgerHelper
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
    public static function getAllPurgerTypes(): array
    {
        $purgerTypes = [
            DummyPurger::class,
            CloudflarePurger::class,
        ];

        $purgerTypes = array_unique(array_merge(
            $purgerTypes,
            Blitz::$plugin->settings->purgerTypes
        ), SORT_REGULAR);

        $event = new RegisterComponentTypesEvent([
            'types' => $purgerTypes,
        ]);
        Event::trigger(static::class, self::EVENT_REGISTER_PURGER_TYPES, $event);

        return $event->types;
    }

    /**
     * Returns all purgers.
     *
     * @return BaseCachePurger[]
     */
    public static function getAllPurgers(): array
    {
        $purgers = [];

        /** @var BaseCachePurger $class */
        foreach (PurgerHelper::getAllPurgerTypes() as $class) {
            if ($class::isSelectable()) {
                $purger = self::createPurger($class);

                if ($purger !== null) {
                    $purgers[] = $purger;
                }
            }
        }

        return $purgers;
    }

    /**
     * Creates a purger of the provided type with the optional settings.
     *
     * @param string $type
     * @param array|null $settings
     *
     * @return CachePurgerInterface|null
     */
    public static function createPurger(string $type, array $settings = null)
    {
        $purger = null;

        try {
            /** @var CachePurgerInterface $purger */
            $purger = Component::createComponent([
                'type' => $type,
                'settings' => $settings ?? [],
            ], CachePurgerInterface::class);
        }
        catch (InvalidConfigException $e) {}
        catch (MissingComponentException $e) {}

        return $purger;
    }
}