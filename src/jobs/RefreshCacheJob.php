<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\jobs;

use Craft;
use craft\helpers\App;
use craft\queue\BaseJob;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\helpers\SiteUriHelper;

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
            $this->cacheIds = Blitz::$plugin->refreshCache->getRefreshableCacheIds(
                $this->cacheIds, $this->elementIds, $this->elementTypes
            );
        }

        if (empty($this->cacheIds)) {
            return;
        }

        // Half time
        $this->setProgress($queue, 0.5);

        // Get cached site URIs from cache IDs
        $siteUris = SiteUriHelper::getCachedSiteUris($this->cacheIds);

        // Delete cache records so we get fresh caches
        Blitz::$plugin->flushCache->deleteCacheIds($this->cacheIds);

        // Clear cached URIs so we get a fresh version
        Blitz::$plugin->cacheStorage->deleteValues($siteUris);

        // Purge the cache
        Blitz::$plugin->cachePurger->purgeUris($siteUris);

        // Trigger afterRefreshCache events
        Blitz::$plugin->refreshCache->afterRefresh($siteUris);
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
