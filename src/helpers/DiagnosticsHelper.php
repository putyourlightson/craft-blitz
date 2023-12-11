<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\helpers;

use Craft;
use craft\base\Element;
use craft\db\ActiveQuery;
use craft\db\Table;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\Json;
use putyourlightson\blitz\records\CacheRecord;
use putyourlightson\blitz\records\ElementCacheRecord;
use putyourlightson\blitz\records\ElementQueryCacheRecord;

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

    public static function getPageElementTypes(): array
    {
        $pageId = Craft::$app->getRequest()->getRequiredParam('pageId');

        return ElementCacheRecord::find()
            ->select(['cacheId', 'count(*) as count', 'type'])
            ->innerJoin(Table::ELEMENTS, 'id = elementId')
            ->where(['cacheId' => $pageId])
            ->groupBy(['type'])
            ->orderBy(['count' => SORT_DESC])
            ->asArray()
            ->all();
    }

    public static function getPageElementQueryTypes(): array
    {
        $pageId = Craft::$app->getRequest()->getRequiredParam('pageId');

        return ElementQueryCacheRecord::find()
            ->select(['cacheId', 'count(*) as count', 'type'])
            ->innerJoinWith('elementQuery')
            ->where(['cacheId' => $pageId])
            ->groupBy(['type'])
            ->orderBy(['count' => SORT_DESC])
            ->asArray()
            ->all();
    }

    public static function getElementOfType(): Element
    {
        $elementType = Craft::$app->getRequest()->getParam('elementType');

        return new $elementType;
    }

    public static function getPagesQuery(int|string $siteId): ActiveQuery
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

    public static function getElementQueriesQuery(int $id, string $elementQueryType): ActiveQuery
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

    public static function getQueryStringsQuery(int|string $siteId): ActiveQuery
    {
        Craft::dd(CacheRecord::find()
            ->select(['REGEXP_SUBSTR(uri, "?.") queryString', 'count(*) as pageCount'])
            ->createCommand()
            ->rawSql
        );
        return CacheRecord::find()
            ->select(['REGEXP_SUBSTR(uri, "?.") queryString', 'count(*) as pageCount'])
            ->where(['siteId' => $siteId])
            ->groupBy('uri');
    }

}
