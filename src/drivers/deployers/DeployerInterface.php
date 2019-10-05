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
     */
    public function deployUris(array $siteUris);

    /**
     * Deploys the cache for a given site ID.
     *
     * @param int $siteId
     */
    public function deploySite(int $siteId);

    /**
     * Deploys the entire cache.
     */
    public function deployAll();

    /**
     * Callable method that copies, commits and pushes the provided site URIs.
     *
     * @param array $siteUris
     * @param callable $setProgressHandler
     *
     * @return int
     */
    public function callable(array $siteUris, callable $setProgressHandler): int;
}
