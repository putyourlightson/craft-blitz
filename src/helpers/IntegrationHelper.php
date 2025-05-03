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
    /**
     * @event RegisterComponentTypesEvent
     */
    public const EVENT_REGISTER_INTEGRATIONS = 'registerIntegrations';

    /**
     * Returns all integrations.
     *
     * @return string[]
     */
    public static function getAllIntegrations(): array
    {
        $integrations = Blitz::$plugin->settings->integrations;

        // Only return integrations that exist (to prevent using legacy integrations).
        foreach ($integrations as $key => $integration) {
            if (!class_exists($integration)) {
                unset($integrations[$key]);
            }
        }

        $event = new RegisterComponentTypesEvent([
            'types' => $integrations,
        ]);
        Event::trigger(static::class, self::EVENT_REGISTER_INTEGRATIONS, $event);

        return $event->types;
    }

    /**
     * Returns active integrations.
     *
     * @return IntegrationInterface[]
     */
    public static function getActiveIntegrations(): array
    {
        $integrations = [];

        /** @var IntegrationInterface $integration */
        foreach (self::getAllIntegrations() as $integration) {
            $enabled = true;

            // Ensure all required plugins are enabled at the provided version or above.
            foreach ($integration::getRequiredPlugins() as $handle) {
                if (!self::isEnabled($handle, 'plugin')) {
                    $enabled = false;
                    break;
                }
            }

            // Ensure all required modules are installed.
            foreach ($integration::getRequiredModules() as $handle) {
                if (!self::isEnabled($handle, 'module')) {
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

    /**
     * Returns whether the plugin or module with the handle is enabled.
     */
    private static function isEnabled(array|string $handle, string $type): bool
    {
        $version = null;

        if (is_array($handle)) {
            $version = $handle['version'] ?? $version;
            $handle = $handle['handle'] ?? '';
        }

        if ($type === 'plugin') {
            $pluginOrModule = Craft::$app->getPlugins()->getPlugin($handle);
        } elseif ($type === 'module') {
            $pluginOrModule = Craft::$app->getModule($handle);
        } else {
            return false;
        }

        if ($pluginOrModule === null) {
            return false;
        }

        if ($version !== null
            && version_compare($pluginOrModule->getVersion(), $version, '<')
        ) {
            return false;
        }

        return true;
    }
}
