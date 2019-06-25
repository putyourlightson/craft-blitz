<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\services;

use Craft;
use craft\base\Component;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\events\OutputEvent;
use putyourlightson\blitz\models\SiteUriModel;

/**
 * @property bool $isCacheableRequest
 * @property SiteUriModel $requestedSiteUri
 */
class OutputCacheService extends Component
{
    // Constants
    // =========================================================================

    /**
     * @event OutputEvent
     */
    const EVENT_BEFORE_OUTPUT = 'beforeOutput';

    // Public Methods
    // =========================================================================

    /**
     * Outputs a given site URI if cached.
     *
     * @param SiteUriModel $siteUri
     */
    public function output(SiteUriModel $siteUri)
    {
        $value = Blitz::$plugin->cacheStorage->get($siteUri);

        $event = new OutputEvent([
            'value' => $value,
        ]);
        $this->trigger(self::EVENT_BEFORE_OUTPUT, $event);

        if (!$event->isValid || !$value) {
            return;
        }

        // Update cache control header
        header('Cache-Control: '.Blitz::$plugin->settings->cacheControlHeader);

        // Update powered by header
        header_remove('X-Powered-By');

        if (Blitz::$plugin->settings->sendPoweredByHeader) {
            $header = Craft::$app->getConfig()->getGeneral()->sendPoweredByHeader ? Craft::$app->name.', ' : '';
            header('X-Powered-By: '.$header.'Blitz');
        }

        // Add cache tag header if set
        $tags = Blitz::$plugin->cacheTags->getSiteUriTags($siteUri);

        if (!empty($tags) && Blitz::$plugin->cachePurger->tagHeaderName) {
            $value = implode(Blitz::$plugin->cachePurger->tagHeaderDelimiter, $tags);
            header(Blitz::$plugin->cachePurger->tagHeaderName.': '.$value);
        }

        // Append served by comment
        if (Blitz::$plugin->settings->outputComments) {
            $value .= '<!-- Served by Blitz -->';
        }

        exit($value);
    }
}
