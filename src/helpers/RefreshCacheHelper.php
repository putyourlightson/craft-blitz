<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\helpers;

use craft\base\Element;
use craft\db\Query;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\Json;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\models\RefreshDataModel;
use putyourlightson\blitz\records\ElementCacheRecord;
use putyourlightson\blitz\records\ElementQueryRecord;
use Throwable;
use TypeError;
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
        $elementIds = $refreshData->getElementIds($elementType);
        $tableColumn = ElementCacheRecord::tableName() . '.elementId';
        $condition = [$tableColumn => $elementIds];

        if ($refreshData->getCombinedIsChangedByFields($elementType)) {
            $condition = ['or'];
            $elementIdsNotChangedByFields = [];

            foreach ($elementIds as $elementId) {
                $isChangedByFields = $refreshData->getIsChangedByFields($elementType, $elementId);
                if ($isChangedByFields) {
                    $changedFields = $refreshData->getChangedFields($elementType, $elementId);
                    $condition[] = [
                        'and',
                        [$tableColumn => $elementId],
                        ['fieldId' => $changedFields],
                    ];
                } else {
                    $elementIdsNotChangedByFields[] = $elementId;
                }
            }

            $condition[] = [$tableColumn => $elementIdsNotChangedByFields];
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

        // Ignore element queries linked to cache IDs that we already have, or not linked to any cache IDs.
        $query->innerJoinWith('elementQueryCaches', false)
            ->andWhere([
                'not',
                ['cacheId' => $ignoreCacheIds],
            ]);

        // Limit to queries without any sources or with sources in `sourceIds`.
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
     * Returns cache IDs for an element query record using the provided refresh data.
     *
     * @return int[]
     */
    public static function getElementQueryCacheIds(ElementQueryRecord $elementQueryRecord, RefreshDataModel $refreshData): array
    {
        // Ensure class still exists as a plugin may have been removed since being saved
        if (!class_exists($elementQueryRecord->type)) {
            $elementQueryRecord->delete();

            return [];
        }

        $params = Json::decodeIfJson($elementQueryRecord->params);

        // If JSON decoding is unsuccessful then delete the record.
        if (!is_array($params)) {
            $elementQueryRecord->delete();

            return [];
        }

        /** @var Element $elementType */
        $elementType = $elementQueryRecord->type;
        $elementQuery = self::getElementQueryWithParams($elementType, $params);

        if ($elementQuery === null) {
            $elementQueryRecord->delete();

            return [];
        }

        $elementQueryIds = [];
        /**
         * Execute the element query, deleting the record if any exception is thrown.
         * Avoid calling the `ids()` method, as it catches exceptions.
         *
         * @see Query::column()
         */
        try {
            $elementQueryIds = $elementQuery
                ->select(['elements.id' => 'elements.id'])
                ->createCommand()
                ->queryColumn();
        } catch (Throwable) {
            $elementQueryRecord->delete();
        }

        if (empty($elementQueryIds)) {
            return [];
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
     * Returns an element query of the provided element type with the params applied.
     */
    public static function getElementQueryWithParams(string $elementType, array $params): ?ElementQueryInterface
    {
        /** @var Element $elementType */
        $elementQuery = $elementType::find();

        // Get elements with all statuses
        // https://github.com/putyourlightson/craft-blitz/issues/527
        $elementQuery->status(null);

        // Assign params, catching any missing types.
        // https://github.com/putyourlightson/craft-blitz/issues/579
        try {
            foreach ($params as $key => $val) {
                /** @throws TypeError if the type does not exist. */
                $elementQuery->{$key} = $val;
            }
        } catch (TypeError $exception) {
            Blitz::$plugin->log('Element query param `' . $key . '` could not be applied: ' . $exception->getMessage(), [], Logger::LEVEL_ERROR);

            return null;
        }

        return $elementQuery;
    }
}
