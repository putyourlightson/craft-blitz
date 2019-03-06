<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\helpers;

use Craft;
use craft\elements\User;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\models\SiteUriModel;

/**
 * @property bool $isCacheableRequest
 * @property SiteUriModel $requestedSiteUri
 */
class RequestHelper
{
    // Public Methods
    // =========================================================================

    /**
     * Returns whether the request is cacheable.
     *
     * @return bool
     */
    public static function getIsCacheableRequest(): bool
    {
        // Ensure this is a cacheable site request
        if (!self::_getIsCacheableSiteRequest()) {
            return false;
        }

        // Ensure the response is not an error
        if (!Craft::$app->getResponse()->getIsOk()) {
            return false;
        }

        /** @var User|null $user */
        $user = Craft::$app->getUser()->getIdentity();

        // Ensure that if user is logged in then debug toolbar is not enabled
        if ($user !== null && $user->getPreference('enableDebugToolbarForSite')) {
            return false;
        }

        if (!Blitz::$plugin->settings->cachingEnabled) {
            return false;
        }

        if (Blitz::$plugin->settings->queryStringCaching == 0
            && Craft::$app->getRequest()->getQueryStringWithoutPath() !== '') {
            return false;
        }

        return true;
    }

    /**
     * Returns the requested site URI.
     *
     * @return SiteUriModel
     */
    public static function getRequestedSiteUri(): SiteUriModel
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
     * Returns whether the user can access the site.
     *
     * @return bool
     */
    public static function getCanAccessSite(): bool
    {
        if (Craft::$app->getIsLive()) {
            return true;
        }

        return Craft::$app->getUser()->checkPermission('accessSiteWhenSystemIsOff');
    }

    // Private Methods
    // =========================================================================

    /**
     * Returns whether the request is cacheable site request.
     *
     * @return bool
     */
    private static function _getIsCacheableSiteRequest(): bool
    {
        $request = Craft::$app->getRequest();

        // Ensure this is a front-end get request that is not a console request or an action request or live preview
        return ($request->getIsSiteRequest() && $request->getIsGet() && !$request->getIsConsoleRequest() && !$request->getIsActionRequest() && !$request->getIsLivePreview());
    }
}