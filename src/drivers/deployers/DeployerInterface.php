<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\drivers\deployers;

use craft\base\SavableComponentInterface;
use putyourlightson\blitz\models\SiteUriModel;

interface DeployerInterface extends SavableComponentInterface
{
    /**
     * Deploys the cache given an array of site URIs.
     *
     * @param SiteUriModel[] $siteUris
     */
    public function deployUris(array $siteUris, callable $setProgressHandler = null, bool $queue = true);

    /**
     * Deploys the cache for a given site ID.
     */
    public function deploySite(int $siteId, callable $setProgressHandler = null);

    /**
     * Deploys the entire cache.
     */
    public function deployAll(callable $setProgressHandler = null);

    /**
     * Tests the deployer settings.
     */
    public function test(): bool;
}
