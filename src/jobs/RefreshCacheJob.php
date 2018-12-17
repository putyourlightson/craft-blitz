<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\jobs;

use Craft;
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

        /*
         * Get element query records of the element types without already saved cache IDs and without eager-loading.
         * This is used for detecting if pages with element queries need to be updated.
         */
        $elementQueryRecords = ElementQueryRecord::find()
            ->select(['id', 'query'])
            ->where(['type' => $this->elementTypes])
            ->innerJoinWith([
                'elementQueryCaches' => function(ActiveQuery $query) {
                    $query->where(['not', ['cacheId' => $this->cacheIds]]);
                }
            ], false)
            ->all();

        /** @var ElementQueryRecord[] $elementQueryRecords */
        foreach ($elementQueryRecords as $elementQueryRecord) {
            /** @var ElementQuery|false $query */
            /** @noinspection UnserializeExploitsInspection */
            $query = @unserialize(base64_decode($elementQueryRecord->query));

            // Ensure the unserialization worked
            if ($query === false) {
                continue;
            }

            // If the the query has an offset then add it to the limit and make it null
            if ($query->offset) {
                if ($query->limit) {
                    $query->limit($query->limit + $query->offset);
                }
                $query->offset(null);
            }

            // If one or more of the element IDs are in the query's results
            $matchedElementIds = array_intersect($this->elementIds, $query->ids());

            if (!empty($matchedElementIds)) {
                // Get related element query cache records
                $elementQueryCacheRecords = $elementQueryRecord->elementQueryCaches;

                // Add cache IDs to the array that do not already exist
                foreach ($elementQueryCacheRecords as $elementQueryCacheRecord) {
                    if (!in_array($elementQueryCacheRecord->cacheId, $this->cacheIds, true)) {
                        $this->cacheIds[] = $elementQueryCacheRecord->cacheId;
                    }
                }
            }
        }

        if (empty($this->cacheIds)) {
            return;
        }

        // Get URLs to clear from cache IDs
        $urls = [];

        /** @var CacheRecord[] $cacheRecords */
        $cacheRecords = CacheRecord::find()
            ->select('uri, siteId')
            ->where(['id' => $this->cacheIds])
            ->all();

        $total = count($cacheRecords);
        $count = 0;

        foreach ($cacheRecords as $cacheRecord) {
            $urls[] = Blitz::$plugin->cache->getSiteUrl($cacheRecord->siteId, $cacheRecord->uri);

            // Delete cached file so we get a fresh file cache
            Blitz::$plugin->file->deleteFileByUri($cacheRecord->siteId, $cacheRecord->uri);

            $count++;
            $this->setProgress($queue, $count / $total);
        }

        // Trigger afterRefreshCache event
        Blitz::$plugin->cache->afterRefreshCache($this->cacheIds);

        // Delete cache records so we get fresh caches
        CacheRecord::deleteAll(['id' => $this->cacheIds]);

        $settings = Blitz::$plugin->getSettings();

        if ($settings->cachingEnabled && $settings->warmCacheAutomatically) {
            Craft::$app->getQueue()->push(new WarmCacheJob(['urls' => $urls]));
        }
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
