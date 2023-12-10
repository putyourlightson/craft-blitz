<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\utilities;

use Craft;
use craft\base\Element;
use craft\base\Utility;
use putyourlightson\blitz\assets\BlitzAsset;
use putyourlightson\blitz\helpers\DiagnosticsHelper;
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
                    'page' => DiagnosticsHelper::getPage($id),
                    'elementType' => new $elementType(),
                ]);
            }

            $elementQueryType = Craft::$app->getRequest()->getParam('elementQueryType');
            if ($elementQueryType) {
                /** @var Element $elementQueryType */
                return Craft::$app->getView()->renderTemplate('blitz/_utilities/diagnostics/elementQueryType', [
                    'page' => DiagnosticsHelper::getPage($id),
                    'elementQueryType' => new $elementQueryType(),
                ]);
            }

            return Craft::$app->getView()->renderTemplate('blitz/_utilities/diagnostics/page', [
                'page' => DiagnosticsHelper::getPage($id),
                'elementTypes' => DiagnosticsHelper::getElementTypes($id),
                'elementQueryTypes' => DiagnosticsHelper::getElementQueryTypes($id),
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
}
