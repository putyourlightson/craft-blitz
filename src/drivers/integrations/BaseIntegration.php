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
    public static function getRequiredPlugins(): array
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
