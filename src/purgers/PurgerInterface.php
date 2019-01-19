<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\purgers;

interface PurgerInterface
{
    /**
     * Purges the cache given an array of URLs.
     *
     * @param array $urls
     */
    public function purgeUrls(array $urls);

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