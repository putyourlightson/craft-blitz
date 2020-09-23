<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\helpers;

use Craft;
use craft\events\RegisterComponentTypesEvent;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\drivers\integrations\IntegrationInterface;
use yii\base\Event;

class IntegrationHelper
{
    // Constants
    // =========================================================================

    /**
     * @event RegisterComponentTypesEvent
     */
    const EVENT_REGISTER_INTEGRATIONS = 'registerIntegrations';

    // Public Methods
    // =========================================================================

    /**
     * Returns all integrations.
     *
     * @return string[]
     */
    public static function getAllIntegrations(): array
    {
        $integrations = Blitz::$plugin->settings->integrations;

        $event = new RegisterComponentTypesEvent([
            'types' => $integrations,
        ]);
        Event::trigger(static::class, self::EVENT_REGISTER_INTEGRATIONS, $event);

        $integrations = $event->types;

        return $integrations;
    }

    /**
     * Returns active integrations.
     *
     * @return string[]
     */
    public static function getActiveIntegrations(): array
    {
        $integrations = [];
        $pluginsService = Craft::$app->getPlugins();

        /** @var IntegrationInterface $integration */
        foreach (self::getAllIntegrations() as $integration) {
            $enabled = true;

            // Ensure all required plugins are enabled at the provided version or above
            foreach ($integration::getRequiredPlugins() as $handle) {
                $version = 0;

                if (is_array($handle)) {
                    $version = $handle['version'] ?? $version;
                    $handle = $handle['handle'] ?? '';
                }

                $plugin = $pluginsService->getPlugin($handle);

                if ($plugin === null) {
                    $enabled = false;
                    break;
                }

                if (version_compare($plugin->getVersion(), $version, '<')) {
                    $enabled = false;
                    break;
                }
            }

            if ($enabled) {
                $integrations[] = $integration;
            }
        }

        return $integrations;
    }
}
