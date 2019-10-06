<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\drivers\warmers;

use craft\base\SavableComponentInterface;
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
     * @param callable|null $setProgressHandler
     */
    public function warmUris(array $siteUris, int $delay = null, callable $setProgressHandler = null);

    /**
     * Warms the cache for a given site ID.
     *
     * @param int $siteId
     * @param int|null $delay
     * @param callable|null $setProgressHandler
     */
    public function warmSite(int $siteId, int $delay = null, callable $setProgressHandler = null);

    /**
     * Warms the entire cache.
     *
     * @param int|null $delay
     * @param callable|null $setProgressHandler
     */
    public function warmAll(int $delay = null, callable $setProgressHandler = null);
}
