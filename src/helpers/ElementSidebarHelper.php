<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\helpers;

use Craft;
use craft\base\Element;
use craft\base\Event;
use craft\elements\Category;
use craft\elements\Entry;
use craft\events\DefineElementEditorHtmlEvent;
use craft\helpers\DateTimeHelper;
use craft\helpers\Html;
use craft\helpers\UrlHelper;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\drivers\storage\DummyStorage;
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
     * @const string[]
     */
    public const ELIGIBLE_ELEMENT_TYPES = [
        Entry::class,
        Category::class,
        'craft\commerce\elements\Product',
        'putyourlightson\campaign\elements\CampaignElement',
        'putyourlightson\campaign\elements\MailingListElement',
    ];

    /**
     * Returns the HTML for the sidebar.
     */
    public static function getSidebarHtml(Element $element): string
    {
        if (Blitz::$plugin->settings->cachingEnabled === false) {
            return '';
        }

        if (Blitz::$plugin->cacheStorage instanceof DummyStorage) {
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
        if ($siteUri->uri === Element::HOMEPAGE_URI) {
            $siteUri->uri = '';
        }
        $cachedValue = Blitz::$plugin->cacheStorage->get($siteUri);

        /** @var CacheRecord|null $cacheRecord */
        $cacheRecord = CacheRecord::find()
            ->where($siteUri->toArray())
            ->one();

        $dateCached = $cacheRecord ? DateTimeHelper::toDateTime($cacheRecord->dateCached) : null;
        $expiryDate = $cacheRecord ? DateTimeHelper::toDateTime($cacheRecord->expiryDate) : null;

        if (property_exists($element, 'expiryDate')) {
            $elementExpiryDate = DateTimeHelper::toDateTime($element->expiryDate);
            if ($elementExpiryDate) {
                $expiryDate = $expiryDate ? min($elementExpiryDate, $expiryDate) : $elementExpiryDate;
            }
        }

        $html = Craft::$app->getView()->renderTemplate('blitz/_element-sidebar', [
            'cached' => !empty($cachedValue),
            'expired' => $expiryDate && $expiryDate <= DateTimeHelper::toDateTime('now'),
            'isCacheable' => Blitz::$plugin->cacheRequest->getIsCacheableSiteUri($siteUri),
            'pageId' => $cacheRecord->id ?? null,
            'dateCached' => $dateCached,
            'expiryDate' => $expiryDate,
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
