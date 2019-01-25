<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\jobs;

use Craft;
use craft\helpers\App;
use craft\queue\BaseJob;
use putyourlightson\blitz\Blitz;

class RefreshCacheJob extends BaseJob
{
    // Properties
    // =========================================================================

    /**
     * @var int[]
     */
    public $cacheIds = [];

    /**
     * @var int[]
     */
    public $elementIds = [];

    /**
     * @var string[]
     */
    public $elementTypes = [];

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     * @throws \Exception
     * @throws \Throwable
     */
    public function execute($queue)
    {
        App::maxPowerCaptain();

        if (!empty($this->elementIds)) {
            $this->cacheIds = Blitz::$plugin->refreshService->getRefreshableCacheIds(
                $this->cacheIds, $this->elementIds, $this->elementTypes
            );
        }

        if (empty($this->cacheIds)) {
            return;
        }

        // Half time
        $this->setProgress($queue, 0.5);

        // Get cached site URIs from cache IDs
        $siteUris = Blitz::$plugin->refreshService->getCachedSiteUris($this->cacheIds);

        // Delete cache records so we get fresh caches
        Blitz::$plugin->clearService->deleteCacheIds($this->cacheIds);

        // Clear cached URIs so we get a fresh version
        Blitz::$plugin->driver->clearCachedUris($siteUris);

        // Purge the cache
        Blitz::$plugin->purger->purgeUris($siteUris);

        // Trigger afterRefreshCache events
        Blitz::$plugin->refreshService->afterRefreshCache($siteUris);
    }

    // Protected Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    protected function defaultDescription(): string
    {
        return Craft::t('blitz', 'Refreshing Blitz cache');
    }
}
