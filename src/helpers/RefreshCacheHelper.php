<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\helpers;

use craft\base\Element;
use craft\elements\db\ElementQuery;
use craft\helpers\Json;
use Exception;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\models\RefreshDataModel;
use putyourlightson\blitz\records\ElementCacheRecord;
use putyourlightson\blitz\records\ElementQueryRecord;
use yii\log\Logger;

/**
 * This class provides methods that are mostly called by the [[RefreshCacheJob]]
 * class. They have been extracted into this class to make them testable, and
 * they have been added here instead of in [[RefreshCacheService]] since they
 * don’t relate to a current refresh cache request.
 *
 * @since 4.4.0
 */
class RefreshCacheHelper
{
    /**
     * Returns cache IDs for an element type using the provided refresh data.
     * If one or more custom fields caused the elements to change, then only
     * elements that track those fields are returned.
     *
     * @return int[]
     */
    public static function getElementCacheIds(string $elementType, RefreshDataModel $refreshData): array
    {
        $condition = ['or'];

        foreach ($refreshData->getElementIds($elementType) as $elementId) {
            $elementCondition = [
                'and',
                [ElementCacheRecord::tableName() . '.elementId' => $elementId],
            ];

            $isChangedByFields = $refreshData->getIsChangedByFields($elementType, $elementId);

            if ($isChangedByFields) {
                $changedFields = $refreshData->getChangedFields($elementType, $elementId);
                $elementCondition[] = ['fieldId' => $changedFields];
            }

            $condition[] = $elementCondition;
        }

        return ElementCacheRecord::find()
            ->select(ElementCacheRecord::tableName() . '.cacheId')
            ->where($condition)
            ->joinWith('elementFieldCaches')
            ->groupBy(ElementCacheRecord::tableName() . '.cacheId')
            ->column();
    }

    /**
     * Returns element query records of the provided element type using the
     * provided refresh data. If either attributes or custom fields caused the
     * elements to change, then only element queries that depend on those
     * attributes or fields are returned.
     *
     * @return ElementQueryRecord[]
     */
    public static function getElementTypeQueryRecords(string $elementType, RefreshDataModel $refreshData): array
    {
        $ignoreCacheIds = $refreshData->getCacheIds();
        $sourceIds = $refreshData->getSourceIds($elementType);
        $changedAttributes = $refreshData->getCombinedChangedAttributes($elementType);
        $changedFields = $refreshData->getCombinedChangedFields($elementType);
        $isChangedByAttributes = $refreshData->getCombinedIsChangedByAttributes($elementType);
        $isChangedByFields = $refreshData->getCombinedIsChangedByFields($elementType);

        // Get element query records without eager loading
        $query = ElementQueryRecord::find()
            ->where(['type' => $elementType]);

        // Ignore element queries linked to cache IDs that we already have
        $query->innerJoinWith('elementQueryCaches', false)
            ->andWhere([
                'not',
                ['cacheId' => $ignoreCacheIds],
            ]);

        // Limit to queries with no sources or sources in `sourceIds`
        $query->joinWith('elementQuerySources', false)
            ->andWhere([
                'or',
                ['sourceId' => null],
                ['sourceId' => $sourceIds],
            ]);

        // Only limit the query if the elements were changed by attributes and/or fields.
        if ($isChangedByAttributes || $isChangedByFields) {
            // Limit queries to only those with attributes or fields that have changed
            $query->joinWith('elementQueryAttributes', false)
                ->joinWith('elementQueryFields', false)
                ->andWhere([
                    'or',
                    // Any date updated attributes should always be included
                    ['attribute' => ['dateUpdated']],
                    ['attribute' => $changedAttributes],
                    ['fieldId' => $changedFields],
                ]);
        }

        /** @var ElementQueryRecord[] */
        return $query->all();
    }

    /**
     * Returns cache IDs for an element query record using the provided refresh
     * data.
     *
     * @return int[]
     */
    public static function getElementQueryCacheIds(ElementQueryRecord $elementQueryRecord, RefreshDataModel $refreshData): array
    {
        // Ensure class still exists as a plugin may have been removed since being saved
        if (!class_exists($elementQueryRecord->type)) {
            return [];
        }

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

        $elementQueryIds = [];

        // Execute the element query, ignoring any exceptions.
        try {
            $elementQueryIds = $elementQuery->ids();
        } catch (Exception $exception) {
            Blitz::$plugin->log('Element query with ID `' . $elementQueryRecord->id . '` could not be executed: ' . $exception->getMessage(), [], Logger::LEVEL_ERROR);
        }

        $elementIds = $refreshData->getElementIds($elementType);

        // If no element IDs are in the element query’s IDs
        if (empty(array_intersect($elementIds, $elementQueryIds))) {
            return [];
        }

        // Return related element query cache IDs
        return $elementQueryRecord->getElementQueryCaches()
            ->select('cacheId')
            ->column();
    }
}
