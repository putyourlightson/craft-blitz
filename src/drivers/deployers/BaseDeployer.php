<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\drivers\deployers;

use Craft;
use craft\base\SavableComponent;
use putyourlightson\blitz\helpers\SiteUriHelper;

abstract class BaseDeployer extends SavableComponent implements DeployerInterface
{
    // Traits
    // =========================================================================

    use DeployerTrait;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function deployUris(array $siteUris, int $delay = null)
    {
    }

    /**
     * @inheritdoc
     */
    public function deploySite(int $siteId)
    {
        $this->deployUris(SiteUriHelper::getSiteSiteUris($siteId));
    }

    /**
     * @inheritdoc
     */
    public function deployAll()
    {
        $sites = Craft::$app->getSites()->getAllSites();

        foreach ($sites as $site) {
            $this->deploySite($site->id);
        }
    }
}
