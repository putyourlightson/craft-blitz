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

        /*
         * Get element query records of the element types without already saved cache IDs and without eager-loading.
         * This is used for detecting if pages with element queries need to be updated.
         */
        if (!empty($this->elementIds)) {
            $elementQueryRecords = ElementQueryRecord::find()
                ->select(['id', 'type', 'params'])
                ->where(['type' => $this->elementTypes])
                ->innerJoinWith([
                    'elementQueryCaches' => function(ActiveQuery $query) {
                        $query->where(['not', ['cacheId' => $this->cacheIds]]);
                    }
                ], false)
                ->all();

            $total = count($elementQueryRecords);
            $count = 0;

            /** @var ElementQueryRecord[] $elementQueryRecords */
            foreach ($elementQueryRecords as $elementQueryRecord) {
                $count++;
                $this->setProgress($queue, $count / $total);

                // Ensure class still exists as a plugin may have been removed since being saved
                if (!class_exists($elementQueryRecord->type)) {
                    continue;
                }

                /** @var ElementInterface $elementType */
                $elementType = $elementQueryRecord->type;

                /** @var ElementQuery $elementQuery */
                $elementQuery = $elementType::find();

                $params = json_decode($elementQueryRecord->params, true);

                // If json decode failed
                if (!is_array($params)) {
                    continue;
                }

                foreach ($params as $key => $val) {
                    $elementQuery->{$key} = $val;
                }

                // If the element query has an offset then add it to the limit and make it null
                if ($elementQuery->offset) {
                    if ($elementQuery->limit) {
                        $elementQuery->limit($elementQuery->limit + $elementQuery->offset);
                    }
                    $elementQuery->offset(null);
                }

                // If one or more of the element IDs are in the query's results
                if (!empty(array_intersect($this->elementIds, $elementQuery->ids()))) {
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
            $count++;
            $this->setProgress($queue, $count / $total);

            $urls[] = Blitz::$plugin->request->getSiteUrl($cacheRecord->siteId, $cacheRecord->uri);

            // Clear cached URI so we get a fresh file version
            Blitz::$plugin->driver->clearCachedUri($cacheRecord->siteId, $cacheRecord->uri);
        }

        // Purge the cache
        Blitz::$plugin->purger->purgeUrls($urls);

        // Trigger afterRefreshCache event
        Blitz::$plugin->invalidate->afterRefreshCache($this->cacheIds);

        // Delete cache records so we get fresh caches
        CacheRecord::deleteAll(['id' => $this->cacheIds]);

        if (Blitz::$settings->cachingEnabled && Blitz::$settings->warmCacheAutomatically) {
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
