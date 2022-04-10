<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\drivers\deployers;

use Craft;
use craft\base\SavableComponent;
use putyourlightson\blitz\events\RefreshCacheEvent;
use putyourlightson\blitz\helpers\DeployerHelper;
use putyourlightson\blitz\helpers\SiteUriHelper;

abstract class BaseDeployer extends SavableComponent implements DeployerInterface
{
    // Traits
    // =========================================================================

    use DeployerTrait;

    // Constants
    // =========================================================================

    /**
     * @event RefreshCacheEvent
     */
    const EVENT_BEFORE_DEPLOY = 'beforeDeploy';

    /**
     * @event RefreshCacheEvent
     */
    const EVENT_AFTER_DEPLOY = 'afterDeploy';

    /**
     * @event RefreshCacheEvent
     */
    const EVENT_BEFORE_DEPLOY_ALL = 'beforeDeployAll';

    /**
     * @event RefreshCacheEvent
     */
    const EVENT_AFTER_DEPLOY_ALL = 'afterDeployAll';

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function deployUris(array $siteUris, callable $setProgressHandler = null, bool $queue = true)
    {
        $event = new RefreshCacheEvent(['siteUris' => $siteUris]);
        $this->trigger(self::EVENT_BEFORE_DEPLOY, $event);

        if (!$event->isValid) {
            return;
        }

        if ($queue) {
            DeployerHelper::addDeployerJob($siteUris, 'deployUrisWithProgress');
        }
        else {
            $this->deployUrisWithProgress($siteUris, $setProgressHandler);
        }

        if ($this->hasEventHandlers(self::EVENT_AFTER_DEPLOY)) {
            $this->trigger(self::EVENT_AFTER_DEPLOY, $event);
        }
    }

    /**
     * @inheritdoc
     */
    public function deploySite(int $siteId, callable $setProgressHandler = null, bool $queue = true)
    {
        $siteUris = SiteUriHelper::getSiteUrisForSite($siteId, true);
        $this->deployUris($siteUris, $setProgressHandler, $queue);
    }

    /**
     * @inheritdoc
     */
    public function deployAll(callable $setProgressHandler = null, bool $queue = true)
    {
        $event = new RefreshCacheEvent();
        $this->trigger(self::EVENT_BEFORE_DEPLOY_ALL, $event);

        if (!$event->isValid) {
            return;
        }

        $siteUris = SiteUriHelper::getAllSiteUris(true);

        $this->deployUris($siteUris, $setProgressHandler, $queue);

        if ($this->hasEventHandlers(self::EVENT_AFTER_DEPLOY_ALL)) {
            $this->trigger(self::EVENT_AFTER_DEPLOY_ALL, $event);
        }
    }

    /**
     * Deploys site URIs with progress.
     *
     * @param array $siteUris
     * @param callable|null $setProgressHandler
     */
    public function deployUrisWithProgress(array $siteUris, callable $setProgressHandler = null)
    {

    }

    /**
     * @inheritdoc
     */
    public function test(): bool
    {
        return true;
    }
}
