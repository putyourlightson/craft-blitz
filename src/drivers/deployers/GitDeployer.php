<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\drivers\deployers;

use Craft;
use putyourlightson\blitz\helpers\DeployerHelper;
use putyourlightson\blitz\helpers\SiteUriHelper;

/**
 * @property mixed $settingsHtml
 */
class GitDeployer extends BaseDeployer
{
    // Properties
    // =========================================================================

    /**
     * @var array
     */
    public $gitSettings = [];

    // Static
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('blitz', 'Git Repository');
    }

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function deployUris(array $siteUris, int $delay = null)
    {
        $this->addDriverJob($siteUris, $delay);
    }

    /**
     * @inheritdoc
     */
    public function deploySite(int $siteId)
    {
        $this->deployUris(SiteUriHelper::getSiteSiteUris($siteId));
    }

    /**
     * Commit and push the provided site URIs.
     *
     * @param array $siteUris
     * @param callable $setProgressHandler
     *
     * @return int
     */
    public function commitPushSiteUris(array $siteUris, callable $setProgressHandler): int
    {
        $success = 0;

        return $success;
    }

    /**
     * @inheritdoc
     */
    public function getSettingsHtml()
    {
        return Craft::$app->getView()->renderTemplate('blitz/_drivers/deployers/git/settings', [
            'deployer' => $this,
        ]);
    }

    // Protected Methods
    // =========================================================================

    /**
     * Adds a driver job to the queue.
     *
     * @param array $siteUris
     * @param null $delay
     */
    protected function addDriverJob(array $siteUris, $delay = null)
    {
        // Add job to queue with a priority and delay
        DeployerHelper::addDriverJob(
            $siteUris,
            [$this, 'commitPushSiteUris'],
            Craft::t('blitz', 'Deploying cached files'),
            $delay
        );
    }
}
