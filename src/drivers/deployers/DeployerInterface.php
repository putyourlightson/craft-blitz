<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\drivers\deployers;

use craft\base\SavableComponentInterface;
use putyourlightson\blitz\models\SiteUriModel;

interface DeployerInterface extends SavableComponentInterface
{
    // Public Methods
    // =========================================================================

    /**
     * Deploys the cache given an array of site URIs.
     *
     * @param SiteUriModel[] $siteUris
     * @param int|null $delay
     * @param callable|null $setProgressHandler
     */
    public function deployUris(array $siteUris, int $delay = null, callable $setProgressHandler = null);

    /**
     * Deploys the cache for a given site ID.
     *
     * @param int $siteId
     * @param int|null $delay
     * @param callable|null $setProgressHandler
     */
    public function deploySite(int $siteId, int $delay = null, callable $setProgressHandler = null);

    /**
     * Deploys the entire cache.
     *
     * @param int|null $delay
     * @param callable|null $setProgressHandler
     */
    public function deployAll(int $delay = null, callable $setProgressHandler = null);
}
