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
use craft\helpers\DateTimeHelper;
use craft\helpers\Db;
use craft\helpers\Json;
use DateTime;
use putyourlightson\blitz\records\CacheRecord;
use putyourlightson\blitz\records\DriverDataRecord;
use putyourlightson\blitz\records\ElementCacheRecord;
use putyourlightson\blitz\records\ElementExpiryDateRecord;
use putyourlightson\blitz\records\ElementQueryCacheRecord;
use putyourlightson\blitz\records\ElementQueryRecord;
use putyourlightson\blitz\services\CacheRequestService;
use putyourlightson\blitzhints\BlitzHints;

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
            ->select(['type', 'count(DISTINCT [[elementId]]) as count'])
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
            ->select(['id', 'uri', 'elementCount', 'elementQueryCount', 'expiryDate'])
            ->leftJoin([
                'elements' => ElementCacheRecord::find()
                    ->select(['cacheId', 'count(*) as elementCount'])
                    ->groupBy(['cacheId']),
            ], 'id = [[elements.cacheId]]')
            ->leftJoin([
                'elementquerycaches' => ElementQueryCacheRecord::find()
                    ->select(['cacheId', 'count(*) as elementQueryCount'])
                    ->groupBy(['cacheId']),
            ], 'id = [[elementquerycaches.cacheId]]')
            ->where(['siteId' => $siteId])
            ->asArray();
    }

    public static function getElementsQuery(int $siteId, string $elementType, ?int $pageId = null): ActiveQuery
    {
        $condition = [
            CacheRecord::tableName() . '.siteId' => $siteId,
            'content.siteId' => $siteId,
            'type' => $elementType,
        ];

        if ($pageId) {
            $condition['cacheId'] = $pageId;
        }

        return ElementCacheRecord::find()
            ->from(['elementcaches' => ElementCacheRecord::tableName()])
            ->select(['elementcaches.elementId', 'elementexpirydates.expiryDate', 'count(*) as count', 'title'])
            ->innerJoinWith('cache')
            ->leftJoin(['elementexpirydates' => ElementExpiryDateRecord::tableName()], '[[elementexpirydates.elementId]] = [[elementcaches.elementId]]')
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
            ->select([ElementQueryRecord::tableName() . '.id', 'params', 'count(*) as count'])
            ->innerJoinWith('cache')
            ->innerJoinWith('elementQuery')
            ->where($condition)
            ->groupBy(ElementQueryRecord::tableName() . '.id')
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

        if ($elementQuery === null) {
            return null;
        }

        try {
            $sql = $elementQuery
                ->select(['elementId' => 'elements.id'])
                ->createCommand()
                ->getRawSql();

            // Return raw SQL with line breaks replaced with spaces.
            return str_replace(["\r\n", "\r", "\n"], ' ', $sql);
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

    public static function getDateForDb(DateTime $dateTime): string
    {
        return Db::prepareDateForDb($dateTime);
    }

    public static function getDateFromDb(string $dateTime): DateTime|false
    {
        return DateTimeHelper::toDateTime($dateTime);
    }

    public static function getHintsCount(): int
    {
        return BlitzHints::getInstance()->hints->getTotalWithoutRouteVariables();
    }

    public static function getDriverDataAction(string $action): ?string
    {
        $record = DriverDataRecord::find()
            ->where(['driver' => 'diagnostics-utility'])
            ->one();

        if ($record === null) {
            return null;
        }

        $data = Json::decodeIfJson($record->data);

        if (!is_array($data)) {
            return null;
        }

        return $data[$action] ?? null;
    }

    public static function updateDriverDataAction(string $action): void
    {
        $record = DriverDataRecord::find()
            ->where(['driver' => 'diagnostics-utility'])
            ->one();

        if ($record === null) {
            $record = new DriverDataRecord();
            $record->driver = 'diagnostics-utility';
        }

        $data = Json::decodeIfJson($record->data);

        if (!is_array($data)) {
            $data = [];
        }

        $data[$action] = Db::prepareDateForDb(new DateTime());
        $record->data = json_encode($data);
        $record->save();
    }
}
