<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\helpers;

use Craft;
use craft\base\Element;
use craft\db\ActiveQuery;
use craft\db\QueryAbortedException;
use craft\db\Table;
use craft\helpers\Json;
use putyourlightson\blitz\records\CacheRecord;
use putyourlightson\blitz\records\ElementCacheRecord;
use putyourlightson\blitz\records\ElementQueryCacheRecord;
use putyourlightson\blitz\services\CacheRequestService;

/**
 * @since 4.10.0
 */
class DiagnosticsHelper
{
    public static function getPagesCount(int $siteId): int
    {
        return CacheRecord::find()
            ->where(['siteId' => $siteId])
            ->count();
    }

    public static function getParamsCount(int $siteId): int
    {
        return count(self::getParams($siteId));
    }

    public static function getElementsCount(int $siteId): int
    {
        return ElementCacheRecord::find()
            ->innerJoinWith('cache')
            ->where(['siteId' => $siteId])
            ->count('DISTINCT [[elementId]]');
    }

    public static function getElementQueriesCount(int $siteId): int
    {
        return ElementQueryCacheRecord::find()
            ->innerJoinWith('cache')
            ->innerJoinWith('elementQuery')
            ->where(['siteId' => $siteId])
            ->count('DISTINCT [[queryId]]');
    }

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
            ->innerJoin(['elements' => Table::ELEMENTS], '[[elements.id]] = [[elementId]]')
            ->where($condition)
            ->groupBy(['type'])
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
            ->select(['type', 'count(DISTINCT [[queryId]]) as count'])
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
            ->where(['siteId' => $siteId])
            ->asArray();
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
            ->from(['elementcaches' => ElementCacheRecord::tableName()])
            ->select(['elementcaches.elementId', 'count(*) as count', 'title'])
            ->innerJoinWith('cache')
            ->innerJoin(['elements' => Table::ELEMENTS], '[[elements.id]] = [[elementcaches.elementId]]')
            ->innerJoin(['content' => Table::CONTENT], '[[content.elementId]] = [[elementcaches.elementId]]')
            ->where($condition)
            ->groupBy(['elementcaches.elementId', 'title'])
            ->asArray();
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
            ->from(['elementquerycaches' => ElementQueryCacheRecord::tableName()])
            ->select(['elementquerycaches.id', 'params', 'count(*) as count'])
            ->innerJoinWith('cache')
            ->innerJoinWith('elementQuery')
            ->where($condition)
            ->groupBy('elementquerycaches.id')
            ->asArray();
    }

    public static function getElementsFromIds(int $siteId, string $elementType, array $elementIds): array
    {
        /** @var Element $elementType */
        return $elementType::find()
            ->siteId($siteId)
            ->status(null)
            ->id($elementIds)
            ->fixedOrder()
            ->indexBy('id')
            ->all();
    }

    public static function getElementQuerySql(string $elementQueryType, string $params): ?string
    {
        $params = Json::decodeIfJson($params);

        // Ensure JSON decode is successful
        if (!is_array($params)) {
            return null;
        }

        $elementQuery = RefreshCacheHelper::getElementQueryWithParams($elementQueryType, $params);

        try {
            return $elementQuery
                ->select(['elementId' => 'elements.id'])
                ->createCommand()
                ->getRawSql();
        } catch (QueryAbortedException) {
            return null;
        }
    }

    public static function getParams(int $siteId): array
    {
        $uris = CacheRecord::find()
            ->select('uri')
            ->where(['siteId' => $siteId])
            ->andWhere(['like', 'uri', '?'])
            ->andWhere(['not', ['like', 'uri', CacheRequestService::CACHED_INCLUDE_PATH . '?action=']])
            ->column();

        $queryStringParams = [];
        foreach ($uris as $uri) {
            $queryString = substr($uri, strpos($uri, '?') + 1);
            parse_str($queryString, $params);
            foreach ($params as $param => $value) {
                $queryStringParams[$param] = [
                    'param' => $param,
                    'count' => ($queryStringParams[$param]['count'] ?? 0) + 1,
                ];
            }
        }

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
