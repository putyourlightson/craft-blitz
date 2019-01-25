<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\drivers\storage;

use putyourlightson\blitz\models\SiteUriModel;

interface CacheStorageInterface
{
    /**
     * Returns the cached value for the provided site and URI.
     *
     * @param SiteUriModel $siteUri
     *
     * @return string
     */
    public function getValue(SiteUriModel $siteUri): string;

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
    public function saveValue(string $value, SiteUriModel $siteUri);

    /**
     * Deletes all cached values.
     */
    public function deleteAll();

    /**
     * Deletes the cache values for the provided site URIs.
     *
     * @param SiteUriModel[]
     */
    public function deleteValues(array $siteUris);
}