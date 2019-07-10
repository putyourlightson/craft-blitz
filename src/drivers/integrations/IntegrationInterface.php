<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\drivers\integrations;

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
     * Returns the class names of required classes.
     *
     * @return string[]
     */
    public static function getRequiredClasses(): array;

    /**
     * Registers events.
     */
    public static function registerEvents();
}
