<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\drivers\integrations;

use Craft;
use craft\base\Component;
use craft\base\ComponentInterface;
use nystudio107\seomatic\events\InvalidateContainerCachesEvent;
use nystudio107\seomatic\services\MetaContainers;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\models\SiteUriModel;
use yii\base\Event;

interface IntegrationInterface
{
    // Public Methods
    // =========================================================================

    /**
     * Returns the handles of required plugins.
     *
     * @return string[]
     */
    public static function getRequiredPluginHandles(): array;

    /**
     * Registers events.
     */
    public static function registerEvents();
}
