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
     * Warms the cache given a site URI.
     *
     * @param SiteUriModel $siteUri
     * @param int|null $delay
     */
    public function warm(SiteUriModel $siteUri, int $delay = null);

    /**
     * Warms the cache given an array of site URIs.
     *
     * @param SiteUriModel[] $siteUris
     * @param int|null $delay
     */
    public function warmUris(array $siteUris, int $delay = null);

    /**
     * Warms the entire cache.
     */
    public function warmAll();

    /**
     * Requests the provided URLs.
     *
     * @param string[] $urls
     * @param callable $setProgressHandler
     *
     * @return int
     */
    public function requestUrls(array $urls, callable $setProgressHandler): int;
}
