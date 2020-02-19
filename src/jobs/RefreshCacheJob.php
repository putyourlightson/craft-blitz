<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\jobs;

use Craft;
use craft\base\Element;
use craft\base\ElementInterface;
use craft\elements\db\ElementQuery;
use craft\helpers\Json;
use craft\queue\BaseJob;
use Exception;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\helpers\ElementTypeHelper;
use putyourlightson\blitz\helpers\SiteUriHelper;
use putyourlightson\blitz\records\ElementQueryCacheRecord;
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
     * @var array
     */
    public $elements = [];

    /**
     * @var bool
     */
    public $clearCache = false;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     *
     * @throws Exception
     * @throws Throwable
     */
    public function execute($queue)
    {
        // Set progress label
        $this->setProgress($queue, 0,
            Craft::t('blitz', 'Clearing cached pages.')
        );

        // Merge in element cache IDs
        foreach ($this->elements as $elementType => $elementData) {
            $this->cacheIds = Blitz::$plugin->refreshCache->getElementCacheIds($elementData['elementIds'], $this->cacheIds);
        }

        // If clear cache is enabled then clear the site URIs early
        if ($this->clearCache) {
            $siteUris = SiteUriHelper::getCachedSiteUris($this->cacheIds);

            Blitz::$plugin->clearCache->clearUris($siteUris);
        }

        // Merge in cache IDs that match any special source tags
        foreach ($this->elements as $elementType => $elementData) {
            $this->cacheIds = array_unique(array_merge(
                $this->cacheIds,
                $this->_getSourceTagCacheIds($elementType, $elementData['sourceIds'])
            ));
        }

        // Merge in cache IDs match element query results
        /** @var ElementInterface|string $elementType */
        foreach ($this->elements as $elementType => $elementData) {
            // If we have element IDs then loop through element queries to check for matches
            if (count($elementData['elementIds'])) {
                $elementQueryRecords = Blitz::$plugin->refreshCache->getElementTypeQueries(
                    $elementType, $elementData['sourceIds'], $this->cacheIds
                );

                if ($total = count($elementQueryRecords)) {
                    $count = 0;

                    // Use sets and the splat operator rather than array_merge for performance (https://goo.gl/9mntEV)
                    $elementQueryCacheIdSets = [[]];

                    foreach ($elementQueryRecords as $elementQueryRecord) {
                        // Merge in element query cache IDs
                        $elementQueryCacheIdSets[] = $this->_getElementQueryCacheIds(
                            $elementQueryRecord, $elementData['elementIds'], $this->cacheIds
                        );

                        $count++;
                        $this->setProgress($queue, $count / $total,
                            Craft::t('blitz', 'Checking {count} of {total} {elementType} queries.', [
                                'count' => $count,
                                'total' => $total,
                                'elementType' => $elementType::lowerDisplayName()
                            ])
                        );
                    }

                    $elementQueryCacheIds = array_merge(...$elementQueryCacheIdSets);
                    $this->cacheIds = array_merge($this->cacheIds, $elementQueryCacheIds);
                }
            }
        }

        // If clear cache is enabled
        if ($this->clearCache) {
            // Set progress label
            $this->setProgress($queue, 1,
                Craft::t('blitz', 'Clearing cached pages.')
            );

            $siteUris = SiteUriHelper::getCachedSiteUris($this->cacheIds);

            // Merge in site URIs of element IDs to ensure that uncached elements are also warmed
            /** @var ElementInterface $elementType */
            foreach ($this->elements as $elementType => $elementData) {
                if ($elementType::hasUris()) {
                    $siteUris = array_merge($siteUris, SiteUriHelper::getElementSiteUris($elementData['elementIds']));
                }
            }

            Blitz::$plugin->refreshCache->refreshSiteUris(array_unique($siteUris, SORT_REGULAR));
        }
        else {
            Blitz::$plugin->refreshCache->expireCacheIds($this->cacheIds);
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
     * Returns cache IDs that match any special source tags
     *
     * @param string $elementType
     * @param array $sourceIds
     *
     * @return int[]
     */
    private function _getSourceTagCacheIds(string $elementType, array $sourceIds)
    {
        $tags = [];

        $sourceIdAttribute = ElementTypeHelper::getSourceIdAttribute($elementType);

        foreach ($sourceIds as $sourceId) {
            $tags[] = $sourceIdAttribute.':'.$sourceId;
        }

        return Blitz::$plugin->cacheTags->getCacheIds($tags);
    }

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
        // Ensure class still exists as a plugin may have been removed since being saved
        if (!class_exists($elementQueryRecord->type)) {
            return [];
        }

        $cacheIds = [];

        /** @var Element $elementType */
        $elementType = $elementQueryRecord->type;

        /** @var ElementQuery $elementQuery */
        $elementQuery = $elementType::find();

        $params = Json::decodeIfJson($elementQueryRecord->params);

        // If json decode failed
        if (!is_array($params)) {
            return [];
        }

        foreach ($params as $key => $val) {
            $elementQuery->{$key} = $val;
        }

        // If the element query has an offset then add it to the limit and make it null
        if ($elementQuery->offset) {
            if ($elementQuery->limit) {
                // Cast values to integers before trying to add them, as they may have been set to strings
                $elementQuery->limit((int)$elementQuery->limit + (int)$elementQuery->offset);
            }

            $elementQuery->offset(null);
        }

        // If one or more of the element IDs are in the element query's IDs
        $elementQueryIds = $elementQuery->ids();

        if (!empty(array_intersect($elementIds, $elementQueryIds))) {
            // Get related element query cache records
            /** @var ElementQueryCacheRecord[] $elementQueryCacheRecords */
            $elementQueryCacheRecords = $elementQueryRecord->getElementQueryCaches()->all();

            // Add cache IDs to the array that do not already exist
            foreach ($elementQueryCacheRecords as $elementQueryCacheRecord) {
                if (!in_array($elementQueryCacheRecord->cacheId, $ignoreCacheIds)) {
                    $cacheIds[] = $elementQueryCacheRecord->cacheId;
                }
            }
        }

        return $cacheIds;
    }

}
