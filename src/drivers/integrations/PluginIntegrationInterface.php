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

interface PluginIntegrationInterface extends ComponentInterface
{
    // Public Methods
    // =========================================================================

    /**
     * Returns whether the plugin is installed.
     */
    public function isPluginInstalled(): bool;
}
