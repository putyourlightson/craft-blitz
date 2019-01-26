<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\helpers;

use craft\base\Element;
use craft\elements\db\ElementQuery;
use putyourlightson\blitz\records\ElementQueryRecord;

class RefreshCacheHelper
{
    // Public Methods
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
    public static function getElementQueryCacheIds(ElementQueryRecord $elementQueryRecord, array $elementIds, array $ignoreCacheIds): array
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

        $params = json_decode($elementQueryRecord->params, true);

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