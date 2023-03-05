<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\drivers\storage;

use putyourlightson\blitz\models\SiteUriModel;

interface CacheStorageInterface
{
    /**
     * Returns the cached value for the provided site URI.
     */
    public function get(SiteUriModel $siteUri): string;

    /**
     * Returns the compressed cached value for the provided site URI.
     *
     * @since 4.5.0
     */
    public function getCompressed(SiteUriModel $siteUri): string;

    /**
     * Saves the cache value for the provided site URI.
     */
    public function save(string $value, SiteUriModel $siteUri, int $duration = null, bool $allowEncoding = true);

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

    /**
     * Returns the widget HTML.
     */
    public function getWidgetHtml(): string;

    /**
     * Returns whether cached values can be compressed.
     */
    public function canCompressCachedValues(): bool;
}
