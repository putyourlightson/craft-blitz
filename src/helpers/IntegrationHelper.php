<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\helpers;

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

    // Static
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
}
