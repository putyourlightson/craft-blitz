<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\services;

use Craft;
use craft\base\Component;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\models\SiteUriModel;

/**
 * @property bool $isCacheableRequest
 * @property SiteUriModel $requestedSiteUri
 */
class OutputCacheService extends Component
{
    // Public Methods
    // =========================================================================

    /**
     * Outputs a given value using native headers and exits.
     *
     * @param string $value
     *
     * @return string
     */
    public function output(string $value)
    {
        // Update powered by header
        header_remove('X-Powered-By');

        if (Blitz::$plugin->settings->sendPoweredByHeader) {
            $header = Craft::$app->getConfig()->getGeneral()->sendPoweredByHeader ? Craft::$app->name.', ' : '';
            header('X-Powered-By: '.$header.'Blitz');
        }

        // Update cache control header
        header('Cache-Control: '.Blitz::$plugin->settings->cacheControlHeader);

        // Append served by comment
        if (Blitz::$plugin->settings->outputComments) {
            $value .= '<!-- Served by Blitz -->';
        }

        exit($value);
    }
}