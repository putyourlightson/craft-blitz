<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\jobs;

use Craft;
use craft\elements\db\ElementQuery;
use craft\helpers\App;
use craft\helpers\UrlHelper;
use craft\queue\BaseJob;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\records\CacheRecord;
use putyourlightson\blitz\records\ElementCacheRecord;
use putyourlightson\blitz\records\ElementQueryRecord;

class RefreshCacheJob extends BaseJob
{
    // Properties
    // =========================================================================

    /**
     * @var int
     */
    public $elementId;

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

        // Get cache IDs to clear
        $cacheIds = [];

        // Get element cache records grouped by cache ID
        $elementCacheRecords = ElementCacheRecord::find()
            ->select('cacheId')
            ->where(['elementId' => $this->elementId])
            ->groupBy('cacheId')
            ->all();

        /** @var ElementCacheRecord[] $elementCacheRecords */
        foreach ($elementCacheRecords as $elementCacheRecord) {
            if (!in_array($elementCacheRecord->cacheId, $cacheIds, true)) {
                $cacheIds[] = $elementCacheRecord->cacheId;
            }
        }

        // Get element query records of the element type without eager loading element query cache records
        $elementQueryRecords = ElementQueryRecord::find()
            ->select('id, query')
            ->where(['type' => Craft::$app->getElements()->getElementTypeById($this->elementId)])
            ->all();

        /** @var ElementQueryRecord[] $elementQueryRecords */
        foreach ($elementQueryRecords as $elementQueryRecord) {
            /** @var ElementQuery|false $query */
            /** @noinspection UnserializeExploitsInspection */
            $query = @unserialize(base64_decode($elementQueryRecord->query));

            // If the element ID is in the query's results
            if ($query === false || in_array($this->elementId, $query->ids(), true)) {
                // Get related element query cache records
                $elementQueryCacheRecords = $elementQueryRecord->elementQueryCache;

                // Add cache IDs to the array that do not already exist
                foreach ($elementQueryCacheRecords as $elementQueryCacheRecord) {
                    if (!in_array($elementQueryCacheRecord->cacheId, $cacheIds, true)) {
                        $cacheIds[] = $elementQueryCacheRecord->cacheId;
                    }
                }
            }
        }

        // Get URLs of caches to clear
        $urls = [];

        /** @var CacheRecord[] $cacheRecords */
        $cacheRecords = CacheRecord::find()
            ->select('id, uri, siteId')
            ->where(['id' => $cacheIds])
            ->all();

        foreach ($cacheRecords as $cacheRecord) {
            $url = UrlHelper::siteUrl($cacheRecord->uri, null, null, $cacheRecord->siteId);
            $urls[] = $url;

            // Delete cached file so we get a fresh file cache
            Blitz::$plugin->file->deleteFileByUri($cacheRecord->siteId, $cacheRecord->uri);

            // Delete cache record so we get a fresh element cache table
            $cacheRecord->delete();
        }

        $settings = Blitz::$plugin->getSettings();

        if ($settings->cachingEnabled AND $settings->warmCacheAutomatically AND count($urls) > 0) {
            Craft::$app->getQueue()->push(new WarmCacheJob(['urls' => $urls]));
        }

        return;
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
