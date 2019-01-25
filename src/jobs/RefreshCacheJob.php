<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\jobs;

use Craft;
use craft\base\ElementInterface;
use craft\elements\db\ElementQuery;
use craft\helpers\App;
use craft\queue\BaseJob;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\records\CacheRecord;
use putyourlightson\blitz\records\ElementQueryRecord;
use yii\db\ActiveQuery;

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
            $this->cacheIds = Blitz::$plugin->invalidate->getRefreshableCacheIds(
                $this->cacheIds, $this->elementIds, $this->elementTypes
            );
        }

        if (empty($this->cacheIds)) {
            return;
        }

        // Half time
        $this->setProgress($queue, 0.5);

        // Get site URIs to clear from cache IDs
        $siteUris = Blitz::$plugin->invalidate->getCachedSiteUris($this->cacheIds);

        $urls = [];

        foreach ($siteUris as $siteUri) {
            // Extracts variables from array
            $siteId = $siteUri['siteId'];
            $uri = $siteUri['uri'];

            $urls[] = Blitz::$plugin->request->getSiteUrl($siteId, $uri);

            // Clear cached URI so we get a fresh file version
            Blitz::$plugin->driver->clearCachedUri($siteId, $uri);
        }

        // Delete cache records so we get fresh caches
        CacheRecord::deleteAll(['id' => $this->cacheIds]);

        // Purge the cache
        Blitz::$plugin->purger->purgeUrls($urls);

        // Trigger afterRefreshCache events
        Blitz::$plugin->invalidate->afterRefreshCache($urls);
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
