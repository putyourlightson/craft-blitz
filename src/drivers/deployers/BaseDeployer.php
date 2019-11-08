<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\drivers\deployers;

use craft\base\SavableComponent;
use putyourlightson\blitz\events\RefreshCacheEvent;
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
    public function deploySite(int $siteId, int $delay = null, callable $setProgressHandler = null)
    {
        $siteUris = SiteUriHelper::getSiteUrisForSite($siteId, true);
        $this->deployUris($siteUris, $delay, $setProgressHandler);
    }

    /**
     * @inheritdoc
     */
    public function deployAll(int $delay = null, callable $setProgressHandler = null)
    {
        $event = new RefreshCacheEvent();
        $this->trigger(self::EVENT_BEFORE_DEPLOY_ALL, $event);

        if (!$event->isValid) {
            return;
        }

        $siteUris = SiteUriHelper::getAllSiteUris(true);
        $this->deployUris($siteUris, $delay, $setProgressHandler);

        if ($this->hasEventHandlers(self::EVENT_AFTER_DEPLOY_ALL)) {
            $this->trigger(self::EVENT_AFTER_DEPLOY_ALL, $event);
        }
    }

    /**
     * @inheritdoc
     */
    public function test(): bool
    {
        return true;
    }
}
