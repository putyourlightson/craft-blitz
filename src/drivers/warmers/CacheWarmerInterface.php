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
     * @param callable|null $setProgressHandler
     * @param int|null $delay
     * @param bool $queue
     */
    public function warmUris(array $siteUris, callable $setProgressHandler = null, int $delay = null, bool $queue = true);

    /**
     * Warms the cache for a given site ID.
     *
     * @param int $siteId
     * @param callable|null $setProgressHandler
     * @param int|null $delay
     * @param bool $queue
     */
    public function warmSite(int $siteId, callable $setProgressHandler = null, int $delay = null, bool $queue = true);

    /**
     * Warms the entire cache.
     *
     * @param callable|null $setProgressHandler
     * @param int|null $delay
     * @param bool $queue
     */
    public function warmAll(callable $setProgressHandler = null, int $delay = null, bool $queue = true);
}
