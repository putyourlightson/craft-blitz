<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\jobs;

use Craft;
use craft\base\ElementInterface;
use craft\helpers\StringHelper;
use craft\queue\BaseJob;
use craft\queue\QueueInterface;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\helpers\ElementTypeHelper;
use putyourlightson\blitz\helpers\RefreshCacheHelper;
use putyourlightson\blitz\helpers\SiteUriHelper;
use putyourlightson\blitz\models\RefreshDataModel;
use yii\queue\RetryableJobInterface;

/**
 * @property-read int $ttr
 */
class RefreshCacheJob extends BaseJob implements RetryableJobInterface
{
    /**
     * @var array
     */
    public array $data = [];

    /**
     * @var bool
     */
    public bool $forceClear = false;

    /**
     * @var bool
     */
    public bool $forceGenerate = false;

    /**
     * @inheritdoc
     */
    public function getTtr(): int
    {
        return Blitz::$plugin->settings->queueJobTtr;
    }

    /**
     * @inheritdoc
     */
    public function canRetry($attempt, $error): bool
    {
        return $attempt < Blitz::$plugin->settings->maxRetryAttempts;
    }

    /**
     * @inheritdoc
     */
    public function execute($queue): void
    {
        $refreshData = RefreshDataModel::createFromData($this->data);

        $this->_populateCacheIdsFromElementCaches($refreshData);

        $clearCache = Blitz::$plugin->settings->clearOnRefresh($this->forceClear);

        // If clear cache is enabled then clear the site URIs early
        if ($clearCache) {
            $siteUris = SiteUriHelper::getCachedSiteUris($refreshData->getCacheIds());
            Blitz::$plugin->clearCache->clearUris($siteUris);
        }

        $this->_populateCacheIdsFromSourceTags($refreshData);
        $this->_populateCacheIdsFromElementQueryCaches($refreshData, $queue);

        // If clear cache is disabled then expire the cache IDs.
        if (!$clearCache) {
            Blitz::$plugin->refreshCache->expireCacheIds($refreshData->getCacheIds());
        }

        $siteUris = SiteUriHelper::getCachedSiteUris($refreshData->getCacheIds());

        // Merge in site URIs of element IDs to ensure that uncached elements are also generated
        foreach ($refreshData->getElementTypes() as $elementType) {
            /** @var ElementInterface|string $elementType */
            if ($elementType::hasUris()) {
                $elementIds = $refreshData->getElementIds($elementType);
                $siteUris = array_merge($siteUris, SiteUriHelper::getElementSiteUris($elementIds));
            }
        }

        $siteUris = array_unique($siteUris, SORT_REGULAR);

        // Purge assets whose image has changed
        $purgeSiteUris = [];
        $assetIds = $refreshData->getAssetsChangedByImage();
        $purgeSiteUris[] = SiteUriHelper::getElementSiteUris($assetIds);

        Blitz::$plugin->refreshCache->refreshSiteUris($siteUris, $purgeSiteUris, $this->forceClear, $this->forceGenerate);
    }

    /**
     * @inheritdoc
     */
    protected function defaultDescription(): string
    {
        return Craft::t('blitz', 'Refreshing Blitz cache');
    }

    /**
     * Populates cache IDs from the element caches.
     */
    private function _populateCacheIdsFromElementCaches(RefreshDataModel $refreshData): void
    {
        foreach ($refreshData->getElementTypes() as $elementType) {
            $cacheIds = RefreshCacheHelper::getElementCacheIds($elementType, $refreshData);
            $refreshData->addCacheIds($cacheIds);
        }
    }

    /**
     * Populates cache IDs from source tags.
     */
    private function _populateCacheIdsFromSourceTags(RefreshDataModel $refreshData): void
    {
        foreach ($refreshData->getElementTypes() as $elementType) {
            $sourceIdAttribute = ElementTypeHelper::getSourceIdAttribute($elementType);

            $tags = [$sourceIdAttribute . ':*'];

            foreach ($refreshData->getSourceIds($elementType) as $sourceId) {
                $tags[] = $sourceIdAttribute . ':' . $sourceId;
            }

            $cacheIds = Blitz::$plugin->cacheTags->getCacheIds($tags);

            $refreshData->addCacheIds($cacheIds);
        }
    }

    /**
     * Populates cache IDs from element query caches.
     */
    private function _populateCacheIdsFromElementQueryCaches(RefreshDataModel $refreshData, QueueInterface $queue): void
    {
        foreach ($refreshData->getElementTypes() as $elementType) {
            $elementIds = $refreshData->getElementIds($elementType);

            if (count($elementIds)) {
                $elementQueryRecords = RefreshCacheHelper::getElementTypeQueryRecords($elementType, $refreshData);

                $total = count($elementQueryRecords);

                if ($total > 0) {
                    $count = 0;

                    foreach ($elementQueryRecords as $elementQueryRecord) {
                        $cacheIds = RefreshCacheHelper::getElementQueryCacheIds($elementQueryRecord, $refreshData);
                        $refreshData->addCacheIds($cacheIds);

                        $count++;
                        $this->setProgress($queue, $count / $total,
                            Craft::t('blitz', 'Checking {count} of {total} {elementType} queries.', [
                                'count' => $count,
                                'total' => $total,
                                // Don't use `lowerDisplayName` which was only introduced in Craft 3.3.17
                                // https://github.com/putyourlightson/craft-blitz/issues/285
                                'elementType' => StringHelper::toLowerCase($elementType::displayName()),
                            ])
                        );
                    }
                }
            }
        }
    }
}
