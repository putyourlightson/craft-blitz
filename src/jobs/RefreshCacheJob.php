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

        // Update URIs for moved entries and affected descendants before resolving the URLs.
        RefreshCacheHelper::updateDescendantSlugsAndUris($refreshData);

        // Resolve element pages once, including updated descendants, for clearing, purging and generation.
        $elementSiteUris = RefreshCacheHelper::getElementSiteUris($refreshData);

        // Clear and synchronously purge element pages before checking dependencies and queries.
        if (Blitz::$plugin->settings->shouldClearOnRefresh($this->forceClear)) {
            if ($elementSiteUris !== []) {
                Blitz::$plugin->clearCache->clearUris($elementSiteUris);
                Blitz::$plugin->cachePurger->purgeUris($elementSiteUris, queue: false);
            }
        }

        // Collect direct dependencies and source-tag matches for the final refresh.
        foreach ($refreshData->getElementTypes() as $elementType) {
            $refreshData->addCacheIds(RefreshCacheHelper::getElementCacheIds($elementType, $refreshData));
            $refreshData->addCacheIds(RefreshCacheHelper::getSourceTagCacheIds($elementType, $refreshData));
        }

        // Find cached pages affected by tracked element queries.
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

        // Site URIs to refresh, keyed by site ID and URI, to maintain order and uniqueness.
        $siteUris = [];

        // Generate element pages first.
        foreach ($elementSiteUris as $siteUri) {
            $siteUris[$siteUri->siteId . ':' . $siteUri->uri] ??= $siteUri;
        }

        // Append affected cached pages without duplicating element pages.
        foreach (SiteUriHelper::getCachedSiteUris($refreshData->getCacheIds()) as $siteUri) {
            $siteUris[$siteUri->siteId . ':' . $siteUri->uri] ??= $siteUri;
        }

        $purgeSiteUris = [];

        if (Blitz::$plugin->settings->shouldPurgeAssetImages()) {
            // Include changed asset images in the final purge.
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
