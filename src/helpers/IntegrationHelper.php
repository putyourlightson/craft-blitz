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

    // Static Methods
    // =========================================================================

    /**
     * Returns all integrations.
     *
     * @return IntegrationInterface[]
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
     * @return IntegrationInterface[]
     */
    public static function getActiveIntegrations(): array
    {
        $integrations = [];
        $pluginsService = Craft::$app->getPlugins();

        foreach (self::getAllIntegrations() as $integration) {
            $active = true;

            foreach ($integration::getRequiredPluginHandles() as $handle) {
                if (!$pluginsService->isPluginInstalled($handle)) {
                    $active = false;
                    break;
                }
            }

            foreach ($integration::getRequiredClasses() as $class) {
                if (!class_exists($class)) {
                    $active = false;
                    break;
                }
            }

            if ($active) {
                $integrations[] = $integration;
            }
        }

        return $integrations;
    }
}
