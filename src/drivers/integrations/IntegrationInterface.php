<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\drivers\integrations;

interface IntegrationInterface
{
    /**
     * Returns the required plugins.
     *
     * Should return an array whose values can be either plugin handles or arrays
     * containing plugin handles and optionally version numbers. For example:
     *
     * - ['feed-me', 'seomatic']
     * - [['handle' => 'feed-me', 'version' => '4.0.0'], 'seomatic']
     *
     * @return string[]|array[]
     */
    public static function getRequiredPlugins(): array;

    /**
     * Returns the required modules.
     *
     * Should return an array whose values are module handles. For example:
     *
     * - ['datastar-module']
     *
     * @return string[]
     */
    public static function getRequiredModules(): array;

    /**
     * Registers events.
     */
    public static function registerEvents();
}
