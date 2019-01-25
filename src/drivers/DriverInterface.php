<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\drivers;

use putyourlightson\blitz\models\SiteUriModel;

interface DriverInterface
{
    /**
     * Returns the cached value for the provided site and URI.
     *
     * @param SiteUriModel $siteUri
     *
     * @return string
     */
    public function getCachedUri(SiteUriModel $siteUri): string;

    /**
     * Returns the utility HTML.
     *
     * @return string
     */
    public function getUtilityHtml(): string;

    /**
     * Saves the cache value for the provided site and URI.
     *
     * @param string $value
     * @param SiteUriModel $siteUri
     */
    public function saveCache(string $value, SiteUriModel $siteUri);

    /**
     * Clears the cache.
     */
    public function clearAllCache();

    /**
     * Clears the cache for the provided site URIs.
     *
     * @param SiteUriModel[]
     */
    public function clearCachedUris(array $siteUris);
}