<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\helpers;

use craft\events\RegisterComponentTypesEvent;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\drivers\deployers\DummyDeployer;
use putyourlightson\blitz\drivers\purgers\BaseCachePurger;
use yii\base\Event;

class DeployerHelper extends BaseDriverHelper
{
    // Constants
    // =========================================================================

    /**
     * @event RegisterComponentTypesEvent
     */
    const EVENT_REGISTER_DEPLOYER_TYPES = 'registerDeployerTypes';

    // Static
    // =========================================================================

    /**
     * Returns all deployer types.
     *
     * @return string[]
     */
    public static function getAllTypes(): array
    {
        $deployerTypes = [
            DummyDeployer::class,
        ];

        $deployerTypes = array_unique(array_merge(
            $deployerTypes,
            Blitz::$plugin->settings->deployerTypes
        ), SORT_REGULAR);

        $event = new RegisterComponentTypesEvent([
            'types' => $deployerTypes,
        ]);
        Event::trigger(static::class, self::EVENT_REGISTER_DEPLOYER_TYPES, $event);

        return $event->types;
    }

    /**
     * Returns all deployer drivers.
     *
     * @return BaseCachePurger[]
     */
    public static function getAllDrivers(): array
    {
        return self::createDrivers(self::getAllTypes());
    }
}
