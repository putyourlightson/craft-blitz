<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\drivers\integrations;

abstract class BaseIntegration implements IntegrationInterface
{
    // Static Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function getRequiredPluginHandles(): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public static function registerEvents()
    {
    }
}
