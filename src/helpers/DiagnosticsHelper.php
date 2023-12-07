<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\helpers;

use craft\base\Element;
use craft\db\Table;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\Json;
use putyourlightson\blitz\records\CacheRecord;
use putyourlightson\blitz\records\ElementCacheRecord;
use putyourlightson\blitz\records\ElementQueryCacheRecord;
use yii\db\Query;

/**
 * @since 4.10.0
 */
class DiagnosticsHelper
{
    public static function getPagesQuery(int|string $siteId): Query
    {
        return CacheRecord::find()
            ->select(['id', 'uri', 'elementCount', 'elementQueryCount'])
            ->leftJoin([
                'elements' => ElementCacheRecord::find()
                    ->select(['cacheId', 'count(*) as elementCount'])
                    ->groupBy(['cacheId']),
            ], 'id = elements.cacheId')
            ->leftJoin([
                'elementQueries' => ElementQueryCacheRecord::find()
                    ->select(['cacheId', 'count(*) as elementQueryCount'])
                    ->groupBy(['cacheId']),
            ], 'id = elementQueries.cacheId')
            ->where(['siteId' => $siteId]);
    }

    public static function getElementsQuery(int $id, string $elementType): ElementQueryInterface
    {
        $elementIds = ElementCacheRecord::find()
            ->select(['id'])
            ->innerJoin(Table::ELEMENTS, 'id = elementId')
            ->where([
                'cacheId' => $id,
                'type' => $elementType,
            ])
            ->asArray()
            ->column();

        /** @var Element $elementType */
        return $elementType::find()
            ->id($elementIds)
            ->status(null);
    }

    public static function getElementQueriesQuery(int $id, string $elementQueryType): Query
    {
        return ElementQueryCacheRecord::find()
            ->select(['params'])
            ->innerJoinWith('elementQuery')
            ->where([
                'cacheId' => $id,
                'type' => $elementQueryType,
            ]);
    }

    public static function getElementQuerySql(string $elementQueryType, string $params): string
    {
        $params = Json::decodeIfJson($params);

        // If json decode failed
        if (!is_array($params)) {
            return 'Invalid params.';
        }

        $elementQuery = RefreshCacheHelper::getElementQueryWithParams($elementQueryType, $params);

        return $elementQuery
            ->select(['elementId' => 'elements.id'])
            ->createCommand()
            ->getRawSql();
    }
}
