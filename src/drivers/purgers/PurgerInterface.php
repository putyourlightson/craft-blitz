<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\drivers\purgers;

use craft\base\SavableComponentInterface;
use putyourlightson\blitz\models\SiteUriModel;

interface PurgerInterface extends SavableComponentInterface
{
    // Static Methods
    // =========================================================================

    /**
     * Returns the template roots.
     */
    public static function getTemplatesRoot(): array;

    // Public Methods
    // =========================================================================

    /**
     * Purges the cache given an array of site URIs.
     *
     * @param SiteUriModel[] $siteUris
     */
    public function purgeUris(array $siteUris);

    /**
     * Purges the entire cache.
     */
    public function purgeAll();

    /**
     * Tests the purge settings.
     *
     * @return bool
     */
    public function test(): bool;
}
