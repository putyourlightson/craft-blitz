<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\utilities;

use Craft;
use craft\base\Utility;
use craft\db\Table;
use putyourlightson\blitz\assets\BlitzAsset;
use putyourlightson\blitz\records\CacheRecord;
use putyourlightson\blitz\records\ElementCacheRecord;
use putyourlightson\blitz\records\ElementQueryCacheRecord;

/**
 * @since 4.10.0
 */
class DiagnosticsUtility extends Utility
{
    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('blitz', 'Blitz Diagnostics');
    }

    /**
     * @inheritdoc
     */
    public static function id(): string
    {
        return 'blitz-diagnostics';
    }

    /**
     * @inheritdoc
     */
    public static function iconPath(): ?string
    {
        $iconPath = Craft::getAlias('@putyourlightson/blitz/resources/icons/diagnostics.svg');

        if (!is_string($iconPath)) {
            return null;
        }

        return $iconPath;
    }

    /**
     * @inheritdoc
     */
    public static function contentHtml(): string
    {
        Craft::$app->getView()->registerAssetBundle(BlitzAsset::class);

        $id = Craft::$app->getRequest()->getParam('id');

        if ($id) {
            return Craft::$app->getView()->renderTemplate('blitz/_utilities/diagnostics/page', [
                'pageUri' => self::getPageUri($id),
                'elementTypes' => self::getPageElements($id),
                'elementQueryTypes' => self::getPageElementQueries($id),
            ]);
        }

        $order = Craft::$app->getRequest()->getParam('order');

        return Craft::$app->getView()->renderTemplate('blitz/_utilities/diagnostics/index', [
            'pages' => self::getPages($order),
        ]);
    }

    public static function getPages(?string $order = null): array
    {
        $order = $order ?? 'elementCount';

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
            ->orderBy([$order => SORT_DESC])
            ->limit(100)
            ->asArray()
            ->all();
    }

    public static function getPageUri(int $id): string
    {
        return CacheRecord::find()
            ->select(['uri'])
            ->where(['id' => $id])
            ->scalar();
    }

    public static function getPageElements(int $id): array
    {
        return ElementCacheRecord::find()
            ->select(['cacheId', 'count(*) as count', 'type'])
            ->innerJoin(Table::ELEMENTS, 'id = elementId')
            ->where(['cacheId' => $id])
            ->groupBy(['type'])
            ->orderBy(['count' => SORT_DESC])
            ->asArray()
            ->all();
    }

    public static function getPageElementQueries(int $id): array
    {
        return ElementQueryCacheRecord::find()
            ->select(['cacheId', 'count(*) as count', 'type'])
            ->innerJoinWith('elementQuery')
            ->where(['cacheId' => $id])
            ->groupBy(['type'])
            ->orderBy(['count' => SORT_DESC])
            ->asArray()
            ->all();
    }
}
