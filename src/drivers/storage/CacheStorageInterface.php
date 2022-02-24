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
     */
    public function get(SiteUriModel $siteUri): string;

    /**
     * Saves the cache value for the provided site and URI.
     */
    public function save(string $value, SiteUriModel $siteUri, int $duration = null);

    /**
     * Deletes the cache values for the provided site URIs.
     *
     * @param SiteUriModel[] $siteUris
     */
    public function deleteUris(array $siteUris);

    /**
     * Deletes all cached values.
     */
    public function deleteAll();

    /**
     * Returns the utility HTML.
     */
    public function getUtilityHtml(): string;
}
