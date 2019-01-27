<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\drivers\purgers;

use putyourlightson\blitz\models\SiteUriModel;

interface CachePurgerInterface
{
    /**
     * Purges the entire cache.
     */
    public function purgeAll();

    /**
     * Purges the cache given a site URI.
     *
     * @param SiteUriModel $siteUri
     */
    public function purge(SiteUriModel $siteUri);

    /**
     * Purges the cache given an array of site URIs.
     *
     * @param SiteUriModel[] $siteUris
     */
    public function purgeUris(array $siteUris);

    /**
     * Tests the purge settings.
     *
     * @return bool
     */
    public function test(): bool;
}