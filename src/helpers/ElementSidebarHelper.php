<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\helpers;

use Craft;
use craft\base\Element;
use craft\base\Event;
use craft\events\DefineHtmlEvent;
use craft\helpers\Db;
use craft\helpers\Html;
use craft\helpers\UrlHelper;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\models\SiteUriModel;
use putyourlightson\blitz\records\CacheRecord;

class ElementSidebarHelper
{
    /**
     * @event DefineHtmlEvent
     */
    public const EVENT_DEFINE_SIDEBAR_HTML = 'defineSidebarHtml';

    /**
     * @event DefineHtmlEvent
     */
    public const EVENT_DEFINE_META_FIELDS_HTML = 'defineMetaFieldsHtml';

    /**
     * Returns the HTML for the sidebar.
     */
    public static function getSidebarHtml(Element $element): string
    {
        $uri = $element->uri;
        if ($uri === null) {
            return '';
        }

        $siteUri = new SiteUriModel([
            'siteId' => $element->siteId,
            'uri' => $element->uri,
        ]);

        $html = Html::beginTag('fieldset', ['class' => 'blitz-element-sidebar']) .
            Html::tag('legend', 'Blitz', ['class' => 'h6']) .
            Html::tag('div', self::metaFieldsHtml($siteUri), ['class' => 'meta']) .
            Html::endTag('fieldset');

        $event = new DefineHtmlEvent([
            'html' => $html,
        ]);
        Event::trigger(self::class, self::EVENT_DEFINE_SIDEBAR_HTML, $event);

        return $event->html;
    }

    private static function metaFieldsHtml(SiteUriModel $siteUri): string
    {
        $cachedValue = Blitz::$plugin->cacheStorage->get($siteUri);

        /** @var CacheRecord|null $cacheRecord */
        $cacheRecord = CacheRecord::find()
            ->where($siteUri->toArray())
            ->one();

        $html = Craft::$app->getView()->renderTemplate('blitz/_element-sidebar', [
            'cached' => !empty($cachedValue),
            'expired' => $cacheRecord && $cacheRecord->expiryDate && $cacheRecord->expiryDate <= Db::prepareDateForDb('now'),
            'dateCached' => $cacheRecord->dateCached ?? null,
            'expiryDate' => $cacheRecord->expiryDate ?? null,
            'refreshActionUrl' => UrlHelper::actionUrl('blitz/cache/refresh-page', [
                'siteId' => $siteUri->siteId,
                'uri' => $siteUri->uri,
                'sidebarPanel' => 1,
            ]),
        ]);

        $event = new DefineHtmlEvent([
            'html' => $html,
        ]);
        Event::trigger(self::class, self::EVENT_DEFINE_META_FIELDS_HTML, $event);

        return $event->html;
    }
}
