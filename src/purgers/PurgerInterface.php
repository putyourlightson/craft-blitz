<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\purgers;

use putyourlightson\blitz\models\SiteUriModel;

interface PurgerInterface
{
    /**
     * Purges the cache given an array of URLs.
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