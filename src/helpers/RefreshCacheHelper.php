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
use yii\db\ActiveQuery;
use yii\log\Logger;

class RefreshCacheHelper
{
    /**
     * Returns cache IDs for an element type using the provided refresh data.
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

            $changedByFields = $refreshData->getChangedByFields($elementType, $elementId);

            if ($changedByFields !== null) {
                if ($changedByFields === true) {
                    $fieldCondition = ['not', ['fieldId' => null]];
                } else {
                    $fieldCondition = ['fieldId' => $changedByFields];
                }

                $elementCondition[] = [
                    'or',
                    ['trackAllFields' => true],
                    $fieldCondition,
                ];
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
     * Returns cache IDs for an element query record using the provided refresh data.
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

    /**
     * Returns element query records of the provided element type using the provided refresh data.
     *
     * @return ElementQueryRecord[]
     */
    public static function getElementTypeQueryRecords(string $elementType, RefreshDataModel $refreshData): array
    {
        $sourceIds = $refreshData->getSourceIds($elementType);
        $ignoreCacheIds = $refreshData->getCacheIds();
        $changedByFields = $refreshData->getCombinedChangedByFields($elementType);

        // Get element query records without eager loading
        $query = ElementQueryRecord::find()
            ->where(['type' => $elementType])
            ->joinWith([
                'elementQuerySources' => function(ActiveQuery $query) use ($sourceIds) {
                    $query->where(['or',
                        ['hasSources' => false],
                        ['sourceId' => $sourceIds],
                    ]);
                },
            ], false)
            // Ignore element queries linked to cache IDs that we already have
            // TODO: verify whether this is too eager
            ->innerJoinWith([
                'elementQueryCaches' => function(ActiveQuery $query) use ($ignoreCacheIds) {
                    $query->where(['not', ['cacheId' => $ignoreCacheIds]]);
                },
            ], false);

        if (!empty($changedByFields)) {
            if ($changedByFields === true) {
                $condition = ['not', 'fieldId' => null];
            } else {
                $condition = ['fieldId' => $changedByFields];
            }

            $query->innerJoinWith([
                'elementQueryFields' => function(ActiveQuery $query) use ($condition) {
                    $query->where($condition);
                },
            ], false);
        }

        /** @var ElementQueryRecord[] */
        return $query->all();
    }
}
