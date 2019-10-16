<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\helpers;

use Craft;
use craft\events\RegisterComponentTypesEvent;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\drivers\deployers\DummyDeployer;
use putyourlightson\blitz\drivers\purgers\BaseCachePurger;
use putyourlightson\blitz\models\SiteUriModel;
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

    /**
     * @param SiteUriModel[] $siteUris
     * @param string $driverMethod
     * @param int|null $delay
     * @param int|null $priority
     */
    public static function addDeployerJob(array $siteUris, string $driverMethod, int $delay = null, int $priority = null)
    {
        $description = Craft::t('blitz', 'Deploying files');

        self::addDriverJob($siteUris, 'deployer', $driverMethod, $description, $delay, $priority);
    }
}
