<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\helpers;

use Craft;
use craft\base\Element;
use craft\db\ActiveQuery;
use craft\db\Table;
use craft\helpers\Json;
use putyourlightson\blitz\records\CacheRecord;
use putyourlightson\blitz\records\ElementCacheRecord;
use putyourlightson\blitz\records\ElementQueryCacheRecord;
use putyourlightson\blitz\records\ElementQueryRecord;

/**
 * @since 4.10.0
 */
class DiagnosticsHelper
{
    public static function getPage(): array|null
    {
        $pageId = Craft::$app->getRequest()->getRequiredParam('pageId');
        $page = CacheRecord::find()
            ->select(['id', 'uri'])
            ->where(['id' => $pageId])
            ->asArray()
            ->one();

        if ($page && $page['uri'] === '') {
            $page['uri'] = '/';
        }

        return $page;
    }

    public static function getElementTypes(int $siteId, ?int $pageId = null): array
    {
        $condition = ['siteId' => $siteId];

        if ($pageId) {
            $condition['cacheId'] = $pageId;
        }

        return ElementCacheRecord::find()
            ->select(['type', 'count(DISTINCT elementId) as count'])
            ->innerJoinWith('cache')
            ->innerJoin(Table::ELEMENTS, Table::ELEMENTS . '.id = elementId')
            ->where($condition)
            ->groupBy(['type', 'elementId'])
            ->orderBy(['count' => SORT_DESC])
            ->asArray()
            ->all();
    }

    public static function getElementQueryTypes(int $siteId, ?int $pageId = null): array
    {
        $condition = ['siteId' => $siteId];

        if ($pageId) {
            $condition['cacheId'] = $pageId;
        }

        return ElementQueryCacheRecord::find()
            ->select(['type', 'count(DISTINCT ' . ElementQueryRecord::tableName() . '.id) as count'])
            ->innerJoinWith('cache')
            ->innerJoinWith('elementQuery')
            ->where($condition)
            ->groupBy(['type'])
            ->orderBy(['count' => SORT_DESC])
            ->asArray()
            ->all();
    }

    public static function getElementOfType(): Element
    {
        $elementType = Craft::$app->getRequest()->getParam('elementType');

        return new $elementType();
    }

    public static function getPagesQuery(int $siteId): ActiveQuery
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

    public static function getElementsQuery(int $siteId, string $elementType, ?int $pageId = null): ActiveQuery
    {
        $condition = [
            CacheRecord::tableName() . '.siteId' => $siteId,
            Table::CONTENT . '.siteId' => $siteId,
            'type' => $elementType,
        ];

        if ($pageId) {
            $condition['cacheId'] = $pageId;
        }

        return ElementCacheRecord::find()
            ->select([ElementCacheRecord::tableName() . '.elementId', 'count(*) as count', 'title'])
            ->innerJoinWith('cache')
            ->innerJoin(Table::ELEMENTS, Table::ELEMENTS . '.id = elementId')
            ->innerJoin(Table::CONTENT, Table::CONTENT . '.elementId = ' . ElementCacheRecord::tableName() . '.elementId')
            ->where($condition)
            ->groupBy(['elementId', 'title'])
            ->asArray();
    }

    public static function getElementsFromIds(int $siteId, string $elementType, array $elementIds): array
    {
        /** @var Element $elementType */
        return $elementType::find()
            ->id($elementIds)
            ->siteId($siteId)
            ->status(null)
            ->indexBy('id')
            ->fixedOrder()
            ->all();
    }

    public static function getElementQueriesQuery(int $siteId, string $elementType, ?int $pageId = null): ActiveQuery
    {
        $condition = [
            'siteId' => $siteId,
            'type' => $elementType,
        ];

        if ($pageId) {
            $condition['cacheId'] = $pageId;
        }

        return ElementQueryCacheRecord::find()
            ->select(['params', 'count(*) as count'])
            ->innerJoinWith('cache')
            ->innerJoinWith('elementQuery')
            ->where($condition)
            ->groupBy('params');
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

    public static function getParams(int $siteId): array
    {
        $rows = CacheRecord::find()
            ->select(['REGEXP_SUBSTR(uri, "(?<=[?]).*") queryString', 'count(*) as count'])
            ->where(['siteId' => $siteId])
            ->groupBy('queryString')
            ->asArray()
            ->all();

        $queryStringParams = [];
        foreach ($rows as $row) {
            parse_str($row['queryString'], $params);
            foreach ($params as $param => $value) {
                $queryStringParams[$param] = $queryStringParams[$param] ?? 0;
                $queryStringParams[$param] += $row['count'];
            }
        }

        arsort($queryStringParams);

        return $queryStringParams;
    }

    public static function getParamPagesQuery(int $siteId, string $param): ActiveQuery
    {
        return CacheRecord::find()
            ->select(['uri'])
            ->where(['siteId' => $siteId])
            ->andWhere(['like', 'uri', $param]);
    }
}
