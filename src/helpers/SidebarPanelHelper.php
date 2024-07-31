<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\helpers;

use Craft;
use craft\base\Element;
use craft\helpers\UrlHelper;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\records\CacheRecord;

class SidebarPanelHelper
{
    public static function getHtml(Element $element): string
    {
        $url = $element->getUrl();
        if ($url === null) {
            return '';
        }

        $siteUri = SiteUriHelper::getSiteUriFromUrl($url);
        $cachedValue = Blitz::$plugin->cacheStorage->get($siteUri);
        $expired = CacheRecord::find()
            ->where($siteUri->getAttributes())
            ->andWhere(['not', ['expiryDate' => null]])
            ->exists();

        return Craft::$app->getView()->renderTemplate('blitz/_sidebar-panel', [
            'cached' => !empty($cachedValue),
            'expired' => $expired,
            'url' => $url,
            'refreshActionUrl' => UrlHelper::actionUrl('blitz/cache/refresh-urls'),
        ]);
    }
}
