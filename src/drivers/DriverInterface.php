<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\drivers;

interface DriverInterface
{
    /**
     * Returns the cached value for a given site and URI.
     *
     * @param int $siteId
     * @param string $uri
     *
     * @return string
     */
    public function getCachedUri(int $siteId, string $uri): string;

    /**
     * Returns the cache count for a given site.
     *
     * @param int $siteId
     *
     * @return int
     */
    public function getCacheCount(int $siteId): int;

    /**
     * Saves the cache value for a given site and URI.
     *
     * @param string $value
     * @param int $siteId
     * @param string $uri
     */
    public function saveCache(string $value, int $siteId, string $uri);

    /**
     * Clears the cache.
     */
    public function clearCache();

    /**
     * Clears the cache for a given site and URI.
     *
     * @param int $siteId
     * @param string $uri
     */
    public function clearCachedUri(int $siteId, string $uri);
}