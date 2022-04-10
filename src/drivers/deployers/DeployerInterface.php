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
     * @param callable|null $setProgressHandler
     * @param bool $queue
     */
    public function deployUris(array $siteUris, callable $setProgressHandler = null, bool $queue = true);

    /**
     * Deploys the cache for a given site ID.
     *
     * @param int $siteId
     * @param callable|null $setProgressHandler
     */
    public function deploySite(int $siteId, callable $setProgressHandler = null);

    /**
     * Deploys the entire cache.
     *
     * @param callable|null $setProgressHandler
     */
    public function deployAll(callable $setProgressHandler = null);

    /**
     * Tests the deployer settings.
     *
     * @return bool
     */
    public function test(): bool;
}
