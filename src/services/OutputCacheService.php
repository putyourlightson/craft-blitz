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

        $response = Craft::$app->getResponse();
        $headers = $response->getHeaders();

        $headers->set('Cache-Control', Blitz::$plugin->settings->cacheControlHeader);

        // Add the Craft `X-Powered-By` header as it will not have been added at this point
        if (Craft::$app->getConfig()->getGeneral()->sendPoweredByHeader) {
            $headers->add('X-Powered-By', Craft::$app->name);
        }

        if (Blitz::$plugin->settings->sendPoweredByHeader) {
            $headers->add('X-Powered-By', Blitz::$plugin->name);
        }

        // Add cache tag header if set
        $tags = Blitz::$plugin->cacheTags->getSiteUriTags($siteUri);

        if (!empty($tags) && Blitz::$plugin->cachePurger->tagHeaderName) {
            $tagsHeader = implode(Blitz::$plugin->cachePurger->tagHeaderDelimiter, $tags);
            $headers->set(Blitz::$plugin->cachePurger->tagHeaderName, $tagsHeader);
        }

        // Append served by comment
        if (Blitz::$plugin->settings->outputComments) {
            $value .= '<!-- Served by Blitz on '.date('c').' -->';
        }

        $response->data = $value;

        Craft::$app->end(0, $response);
    }
}
