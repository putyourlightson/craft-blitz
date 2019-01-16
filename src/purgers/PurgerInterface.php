<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\purgers;

interface PurgerInterface
{
    /**
     * Purges the cache given an array of cache IDs.
     *
     * @param array $cacheIds
     */
    public function purge(array $cacheIds);
}