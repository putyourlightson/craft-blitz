<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\jobs;

use Craft;
use craft\queue\BaseJob;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\helpers\RefreshCacheHelper;
use putyourlightson\blitz\helpers\SiteUriHelper;
use putyourlightson\blitz\models\RefreshDataModel;
use yii\queue\RetryableJobInterface;

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

        // Update descendant URIs first so the refresh includes their new URLs.
        RefreshCacheHelper::updateDescendantSlugsAndUris($refreshData);

        // Collect direct dependencies and source-tag matches.
        foreach ($refreshData->getElementTypes() as $elementType) {
            $refreshData->addCacheIds(RefreshCacheHelper::getElementCacheIds($elementType, $refreshData));
            $refreshData->addCacheIds(RefreshCacheHelper::getSourceTagCacheIds($elementType, $refreshData));
        }

        // Clear identified cache records before checking element queries.
        $cachedSiteUris = [];
        if (Blitz::$plugin->settings->shouldClearOnRefresh($this->forceClear)) {
            $cachedSiteUris = SiteUriHelper::getCachedSiteUris($refreshData->getCacheIds());
            Blitz::$plugin->clearCache->clearUris($cachedSiteUris);
        }

        // Site URIs to refresh, keyed by site ID and URI, to maintain order and uniqueness.
        $siteUris = [];

        // Start with the changed elements’ own pages.
        foreach (RefreshCacheHelper::getElementSiteUris($refreshData) as $siteUri) {
            $siteUris[$siteUri->siteId . ':' . $siteUri->uri] ??= $siteUri;
        }

        foreach ($cachedSiteUris as $siteUri) {
            $siteUris[$siteUri->siteId . ':' . $siteUri->uri] ??= $siteUri;
        }

        // Check potentially expensive element queries after clearing known pages.
        foreach ($refreshData->getElementTypes() as $elementType) {
            if (!empty($refreshData->getElementIds($elementType))) {
                $elementQueryRecords = RefreshCacheHelper::getElementTypeQueryRecords($elementType, $refreshData);
                $total = count($elementQueryRecords);

                foreach ($elementQueryRecords as $index => $elementQueryRecord) {
                    $refreshData->addCacheIds(RefreshCacheHelper::getElementQueryCacheIds($elementQueryRecord, $refreshData));

                    $count = $index + 1;
                    $this->setProgress($queue, $count / $total,
                        Craft::t('blitz', 'Checking {count} of {total} {elementType} queries', [
                            'count' => $count,
                            'total' => $total,
                            'elementType' => $elementType::lowerDisplayName(),
                        ])
                    );
                }
            }
        }

        // Add any pages identified by element queries.
        foreach (SiteUriHelper::getCachedSiteUris($refreshData->getCacheIds()) as $siteUri) {
            $siteUris[$siteUri->siteId . ':' . $siteUri->uri] ??= $siteUri;
        }

        $purgeSiteUris = [];

        if (Blitz::$plugin->settings->shouldPurgeAssetImages()) {
            // Purge assets whose image has changed
            $assetIds = $refreshData->getAssetsChangedByFile();
            $purgeSiteUris = SiteUriHelper::getAssetSiteUris($assetIds);
        }

        Blitz::$plugin->refreshCache->refreshSiteUris(
            array_values($siteUris),
            $purgeSiteUris,
            $this->forceClear,
            $this->forceGenerate,
        );
    }

    /**
     * @inheritdoc
     */
    protected function defaultDescription(): string
    {
        return Craft::t('blitz', 'Refreshing Blitz cache');
    }
}
