<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\helpers;

use Craft;
use craft\base\Element;
use craft\helpers\UrlHelper;
use DateTime;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\models\SiteUriModel;
use putyourlightson\blitz\records\CacheRecord;

class SidebarPanelHelper
{
    public static function getHtml(Element $element): string
    {
        $uri = $element->uri;
        if ($uri === null) {
            return '';
        }

        $siteUri = new SiteUriModel([
            'siteId' => $element->siteId,
            'uri' => $uri,
        ]);
        $cachedValue = Blitz::$plugin->cacheStorage->get($siteUri);
        $cacheRecord = CacheRecord::find()
            ->where($siteUri->toArray())
            ->one();

        return Craft::$app->getView()->renderTemplate('blitz/_sidebar-panel', [
            'cached' => !empty($cachedValue),
            'expired' => $cacheRecord && $cacheRecord->expiryDate && $cacheRecord->expiryDate <= (new DateTime()),
            'dateCached' => $cacheRecord->dateCached ?? null,
            'expiryDate' => $cacheRecord->expiryDate ?? null,
            'refreshActionUrl' => UrlHelper::actionUrl('blitz/cache/refresh-site-uri', [
                'siteId' => $element->siteId,
                'uri' => $uri,
            ]),
        ]);
    }
}
