<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\drivers\integrations;

use Craft;
use craft\base\Component;

abstract class BasePluginIntegration extends Component implements PluginIntegrationInterface
{
    // Constants
    // =========================================================================

    /**
     * @const string
     */
    const PLUGIN_HANDLE = '';

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function isPluginInstalled(): bool
    {
        return Craft::$app->getPlugins()->getPlugin(self::PLUGIN_HANDLE) !== null;
    }
}
