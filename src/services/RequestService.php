<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\services;

use Craft;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\models\SiteUriModel;

class RequestService
{
    // Public Methods
    // =========================================================================

    /**
     * Returns whether the request is cacheable.
     *
     * @return bool
     */
    public function getIsCacheableRequest(): bool
    {
        $request = Craft::$app->getRequest();
        $response = Craft::$app->getResponse();

        // Ensure this is a front-end get that is not a console request or an action request or live preview and returns status 200
        if (!$request->getIsSiteRequest() || !$request->getIsGet() || $request->getIsActionRequest() || $request->getIsLivePreview() || !$response->getIsOk()) {
            return false;
        }

        $user = Craft::$app->getUser()->getIdentity();

        // Ensure that if user is logged in then debug toolbar is not enabled
        if ($user !== null && $user->getPreference('enableDebugToolbarForSite')) {
            return false;
        }

        if (!Blitz::$plugin->settings->cachingEnabled) {
            return false;
        }

        if (!Blitz::$plugin->settings->queryStringCaching === 0 && $request->getQueryStringWithoutPath() !== '') {
            return false;
        }

        return true;
    }

    /**
     * Gets the requested site URI.
     *
     * @return SiteUriModel
     */
    public function getRequestedSiteUri(): SiteUriModel
    {
        $site = Craft::$app->getSites()->getCurrentSite();
        $url = Craft::$app->getRequest()->getAbsoluteUrl();

        // Remove the query string if unique query strings should be cached as the same page
        if (Blitz::$plugin->settings->queryStringCaching == 2) {
            $url = preg_replace('/\?.*/', '', $url);
        }

        // Remove site base URL
        $baseUrl = trim(Craft::getAlias($site->baseUrl), '/');
        $uri = str_replace($baseUrl, '', $url);

        // Trim slashes from the beginning and end of the URI
        $uri = trim($uri, '/');

        return new SiteUriModel([
            'siteId' => $site->id,
            'uri' => $uri,
        ]);
    }

    /**
     * Outputs a given value.
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
            $header = Craft::$app->getConfig()->getGeneral()->sendPoweredByHeader ? 'Craft CMS, ' : '';
            header('X-Powered-By: '.$header.'Blitz');
        }

        // Update cache control header
        header('Cache-Control: '.Blitz::$plugin->settings->cacheControlHeader);

        exit($value.'<!-- Served by Blitz -->');
    }
}