<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\drivers\purgers;

use craft\base\SavableComponentInterface;
use putyourlightson\blitz\models\SiteUriModel;

interface CachePurgerInterface extends SavableComponentInterface
{
    /**
     * Purges the cache given an array of site URIs.
     *
     * @param SiteUriModel[] $siteUris
     */
    public function purgeUris(array $siteUris);

    /**
     * Purges the cache for a given site ID.
     */
    public function purgeSite(int $siteId);

    /**
     * Purges the entire cache.
     */
    public function purgeAll();

    /**
     * Tests the purger settings.
     */
    public function test(): bool;
}
