<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\jobs;

use Craft;
use craft\base\Element;
use craft\elements\db\ElementQuery;
use craft\helpers\App;
use craft\helpers\Json;
use craft\queue\BaseJob;
use Exception;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\helpers\SiteUriHelper;
use putyourlightson\blitz\records\ElementQueryRecord;
use Throwable;

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

    /**
     * @var bool
     */
    public $forceClear = false;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     * @throws Exception
     * @throws Throwable
     */
    public function execute($queue)
    {
        // Set progress label
        $this->setProgress($queue, 0,
            Craft::t('blitz', 'Finding cached pages.')
        );

        // Merge in element cache IDs
        $elementCacheIds = Blitz::$plugin->refreshCache->getElementCacheIds(
            $this->elementIds, $this->cacheIds
        );

        $cacheIds = array_merge($this->cacheIds, $elementCacheIds);

        if (count($this->elementIds)) {
            $elementQueryRecords = Blitz::$plugin->refreshCache->getElementTypeQueries(
                $this->elementTypes, $cacheIds
            );

            if (count($elementQueryRecords)) {
                // Set progress total to number of query records plus one to avoid dividing by zero
                $total = count($elementQueryRecords) + 1;
                $count = 0;

                // Use sets and the splat operator rather than array_merge for performance (https://goo.gl/9mntEV)
                $elementQueryCacheIdSets = [[]];

                foreach ($elementQueryRecords as $elementQueryRecord) {
                    // Merge in element query cache IDs
                    $elementQueryCacheIdSets[] = $this->_getElementQueryCacheIds(
                        $elementQueryRecord, $this->elementIds, $cacheIds
                    );

                    $count++;
                    $this->setProgress($queue, $count / $total,
                        Craft::t('blitz', 'Checked {count} of {total} element queries.', [
                            'count' => $count,
                            'total' => $total,
                        ])
                    );
                }

                $elementQueryCacheIds = array_merge(...$elementQueryCacheIdSets);
                $cacheIds = array_merge($cacheIds, $elementQueryCacheIds);
            }
        }

        if (empty($cacheIds)) {
            return;
        }

        // If clear automatically is enabled or if force clear
        if (Blitz::$plugin->settings->clearCacheAutomatically || $this->forceClear) {
            // Set progress label
            $this->setProgress($queue, 1,
                Craft::t('blitz', 'Clearing {total} cached pages.', [
                    'total' => count($cacheIds)
                ])
            );

            $siteUris = SiteUriHelper::getCachedSiteUris($cacheIds);

            Blitz::$plugin->refreshCache->refreshSiteUris($siteUris);
        }
        else {
            Blitz::$plugin->refreshCache->expireCacheIds($cacheIds);
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

    // Private Methods
    // =========================================================================

    /**
     * Returns cache IDs from a given entry query that contains the provided element IDs,
     * ignoring the provided cache IDs.
     *
     * @param ElementQueryRecord $elementQueryRecord
     * @param array $elementIds
     * @param array $ignoreCacheIds
     *
     * @return int[]
     */
    private function _getElementQueryCacheIds(ElementQueryRecord $elementQueryRecord, array $elementIds, array $ignoreCacheIds): array
    {
        $cacheIds = [];

        // Ensure class still exists as a plugin may have been removed since being saved
        if (!class_exists($elementQueryRecord->type)) {
            return $cacheIds;
        }

        /** @var Element $elementType */
        $elementType = $elementQueryRecord->type;

        /** @var ElementQuery $elementQuery */
        $elementQuery = $elementType::find();

        $params = Json::decodeIfJson($elementQueryRecord->params);

        // If json decode failed
        if (!is_array($params)) {
            return $cacheIds;
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
        if (!empty(array_intersect($elementIds, $elementQuery->ids()))) {
            // Get related element query cache records
            $elementQueryCacheRecords = $elementQueryRecord->elementQueryCaches;

            // Add cache IDs to the array that do not already exist
            foreach ($elementQueryCacheRecords as $elementQueryCacheRecord) {
                if (!in_array($elementQueryCacheRecord->cacheId, $ignoreCacheIds, true)) {
                    $cacheIds[] = $elementQueryCacheRecord->cacheId;
                }
            }
        }

        return $cacheIds;
    }
}
