<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\utilities;

use Craft;
use craft\base\Element;
use craft\base\Utility;
use craft\db\Table;
use putyourlightson\blitz\assets\BlitzAsset;
use putyourlightson\blitz\records\CacheRecord;
use putyourlightson\blitz\records\ElementCacheRecord;
use putyourlightson\blitz\records\ElementQueryCacheRecord;
use putyourlightson\sprig\Sprig;

/**
 * @since 4.10.0
 */
class DiagnosticsUtility extends Utility
{
    public function init(): void
    {
        parent::init();

        Sprig::getInstance()->init();
    }

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
        Sprig::$core->components->setConfig(['requestClass' => 'busy']);

        $id = Craft::$app->getRequest()->getParam('id');

        if ($id) {
            $elementType = Craft::$app->getRequest()->getParam('elementType');
            if ($elementType) {
                /** @var Element $elementType */
                return Craft::$app->getView()->renderTemplate('blitz/_utilities/diagnostics/elementType', [
                    'page' => self::getPage($id),
                    'elementType' => new $elementType(),
                ]);
            }

            $elementQueryType = Craft::$app->getRequest()->getParam('elementQueryType');
            if ($elementQueryType) {
                /** @var Element $elementQueryType */
                return Craft::$app->getView()->renderTemplate('blitz/_utilities/diagnostics/elementQueryType', [
                    'page' => self::getPage($id),
                    'elementQueryType' => new $elementQueryType(),
                ]);
            }

            return Craft::$app->getView()->renderTemplate('blitz/_utilities/diagnostics/page', [
                'page' => self::getPage($id),
                'elementTypes' => self::getElementTypes($id),
                'elementQueryTypes' => self::getElementQueryTypes($id),
            ]);
        }

        $siteId = null;
        $site = Craft::$app->getRequest()->getParam('site');
        if ($site) {
            $site = Craft::$app->getSites()->getSiteByHandle($site);
            $siteId = $site ? $site->id : null;
        }
        if (empty($siteId)) {
            $siteId = Craft::$app->getSites()->getCurrentSite()->id;
        }

        return Craft::$app->getView()->renderTemplate('blitz/_utilities/diagnostics/index', [
            'siteId' => $siteId,
        ]);
    }

    public static function getPage(int $id): array|null
    {
        $page = CacheRecord::find()
            ->select(['id', 'uri'])
            ->where(['id' => $id])
            ->asArray()
            ->one();

        if ($page && $page['uri'] === '') {
            $page['uri'] = '/';
        }

        return $page;
    }

    public static function getElementTypes(int $id): array
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

    public static function getElementQueryTypes(int $id): array
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
