<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\helpers;

use Craft;
use craft\base\Element;
use craft\base\Event;
use craft\events\DefineElementEditorHtmlEvent;
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
        if (Blitz::$plugin->settings->cachingEnabled === false) {
            return '';
        }

        $uri = $element->uri;
        if ($uri === null) {
            return '';
        }

        $html = Html::beginTag('fieldset', ['class' => 'blitz-element-sidebar']) .
            Html::tag('legend', 'Blitz', ['class' => 'h6']) .
            Html::tag('div', self::metaFieldsHtml($element), ['class' => 'meta']) .
            Html::endTag('fieldset');

        $event = new DefineElementEditorHtmlEvent([
            'element' => $element,
            'html' => $html,
        ]);
        Event::trigger(self::class, self::EVENT_DEFINE_SIDEBAR_HTML, $event);

        return $event->html;
    }

    private static function metaFieldsHtml(Element $element): string
    {
        $siteUri = new SiteUriModel([
            'siteId' => $element->siteId,
            'uri' => $element->uri,
        ]);
        $cachedValue = Blitz::$plugin->cacheStorage->get($siteUri);

        /** @var CacheRecord|null $cacheRecord */
        $cacheRecord = CacheRecord::find()
            ->where($siteUri->toArray())
            ->one();

        $html = Craft::$app->getView()->renderTemplate('blitz/_element-sidebar', [
            'cached' => !empty($cachedValue),
            'expired' => $cacheRecord && $cacheRecord->expiryDate && $cacheRecord->expiryDate <= Db::prepareDateForDb('now'),
            'isCacheable' => Blitz::$plugin->cacheRequest->getIsCacheableSiteUri($siteUri),
            'pageId' => $cacheRecord->id,
            'dateCached' => $cacheRecord->dateCached ?? null,
            'expiryDate' => $cacheRecord->expiryDate ?? null,
            'refreshActionUrl' => UrlHelper::actionUrl('blitz/cache/refresh-page', $siteUri->toArray()),
        ]);

        $event = new DefineElementEditorHtmlEvent([
            'element' => $element,
            'html' => $html,
        ]);
        Event::trigger(self::class, self::EVENT_DEFINE_META_FIELDS_HTML, $event);

        return $event->html;
    }
}
