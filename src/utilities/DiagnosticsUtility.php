<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\utilities;

use Craft;
use craft\base\Utility;
use putyourlightson\blitz\assets\BlitzAsset;
use putyourlightson\sprig\Sprig;

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
    public static function icon(): ?string
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

        Sprig::bootstrap();
        Sprig::$core->components->setConfig(['requestClass' => 'busy']);

        $siteId = null;
        $site = Craft::$app->getRequest()->getParam('site');
        if ($site) {
            $site = Craft::$app->getSites()->getSiteByHandle($site);
            $siteId = $site ? $site->id : null;
        }
        if (empty($siteId)) {
            $siteId = Craft::$app->getSites()->getCurrentSite()->id;
        }

        $templateSegments = array_slice(Craft::$app->getRequest()->getSegments(), 2);
        $templatePath = 'blitz/_utilities/diagnostics/' . implode('/', $templateSegments);

        return Craft::$app->getView()->renderTemplate($templatePath, [
            'siteId' => $siteId,
        ]);
    }
}
