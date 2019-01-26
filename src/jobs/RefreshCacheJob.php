<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\jobs;

use Craft;
use craft\helpers\App;
use craft\queue\BaseJob;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\helpers\RefreshCacheHelper;
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

        // Merge in element cache IDs
        $this->cacheIds = array_merge($this->cacheIds,
            Blitz::$plugin->refreshCache->getElementCacheIds(
                $this->elementIds, $this->cacheIds
            )
        );

        if (!empty($this->elementIds)) {
            $elementQueryRecords = Blitz::$plugin->refreshCache->getElementTypeQueries(
                $this->elementTypes, $this->cacheIds
            );

            foreach ($elementQueryRecords as $elementQueryRecord) {
                // Merge in element query cache IDs
                $this->cacheIds = array_merge($this->cacheIds,
                    RefreshCacheHelper::getElementQueryCacheIds(
                        $elementQueryRecord, $this->elementIds, $this->cacheIds
                    )
                );
            }
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
