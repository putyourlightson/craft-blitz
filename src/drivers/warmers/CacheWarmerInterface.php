<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\drivers\warmers;

use craft\base\SavableComponentInterface;
use craft\models\Site;
use putyourlightson\blitz\models\SiteUriModel;

interface CacheWarmerInterface extends SavableComponentInterface
{
    // Public Methods
    // =========================================================================

    /**
     * Warms the cache given an array of site URIs.
     *
     * @param SiteUriModel[] $siteUris
     * @param int|null $delay
     */
    public function warmUris(array $siteUris, int $delay = null);

    /**
     * Warms the cache for a given site ID.
     *
     * @param int $siteId
     */
    public function warmSite(int $siteId);

    /**
     * Warms the entire cache.
     */
    public function warmAll();

    /**
     * Callable method that requests the provided site URIs.
     *
     * @param array $siteUris
     * @param callable $setProgressHandler
     *
     * @return int
     */
    public function callable(array $siteUris, callable $setProgressHandler): int;
}
