<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\drivers\generators;

use craft\base\SavableComponentInterface;
use putyourlightson\blitz\models\SiteUriModel;

interface CacheGeneratorInterface extends SavableComponentInterface
{
    /**
     * Generates the cache given an array of site URIs.
     *
     * @param SiteUriModel[] $siteUris
     */
    public function generateUris(array $siteUris, callable $setProgressHandler = null, bool $queue = true);

    /**
     * Generates the cache for a given site ID.
     */
    public function generateSite(int $siteId, callable $setProgressHandler = null, bool $queue = true);

    /**
     * Generates the entire cache.
     */
    public function generateAll(callable $setProgressHandler = null, bool $queue = true);
}
