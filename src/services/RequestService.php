<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\services;

use Craft;
use craft\web\Request;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\models\SiteUriModel;

/**
 * @property bool $isCacheableRequest
 * @property SiteUriModel $requestedSiteUri
 */
class RequestService extends Request
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
        // Ensure this is a front-end get request that is not a console request or an action request or live preview
        if (!$this->getIsSiteRequest() || !$this->getIsGet() || $this->getIsConsoleRequest() || $this->getIsActionRequest() || $this->getIsLivePreview()) {
            return false;
        }

        // Ensure the response is not an error
        if (!Craft::$app->getResponse()->getIsOk()) {
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

        if (!Blitz::$plugin->settings->queryStringCaching === 0 && $this->getQueryStringWithoutPath() !== '') {
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
        $url = $this->getAbsoluteUrl();

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
}