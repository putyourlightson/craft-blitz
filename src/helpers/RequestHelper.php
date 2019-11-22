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
        if (!Blitz::$plugin->settings->cachingEnabled) {
            return false;
        }

        // Ensure this is a cacheable site request
        if (!self::_getIsCacheableSiteRequest()) {
            return false;
        }

        // Ensure the response is not an error
        if (!Craft::$app->getResponse()->getIsOk()) {
            return false;
        }

        $request = Craft::$app->getRequest();

        /** @var User|null $user */
        $user = Craft::$app->getUser()->getIdentity();

        if ($user !== null) {
            // Ensure that if the site is not live that the user has permission to access it
            if (!Craft::$app->getIsLive() && !$user->can('accessSiteWhenSystemIsOff')) {
                Blitz::$plugin->debug('Page not cached because the site is not live and the user does not have permission to access it.');

                return false;
            }

            // Ensure that if user is logged in then debug toolbar is not enabled
            if ($user->getPreference('enableDebugToolbarForSite')) {
                Blitz::$plugin->debug('Page not cached because the debug toolbar is enabled.');

                return false;
            }
        }

        if (!empty($request->getParam('no-cache'))) {
            Blitz::$plugin->debug('Page not cached because a `no-cache` request parameter was provided.');

            return false;
        }

        if (!empty($request->getParam('token'))) {
            Blitz::$plugin->debug('Page not cached because a `token` request parameter was provided.');

            return false;
        }

        if (Blitz::$plugin->settings->queryStringCaching == 0 && !empty($request->getQueryStringWithoutPath())) {
            Blitz::$plugin->debug('Page not cached because a query string was provided with the query string caching setting disabled.');

            return false;
        }

        return true;
    }

    /**
     * Returns the requested site URI.
     *
     * @return SiteUriModel|null
     */
    public static function getRequestedSiteUri()
    {
        $url = Craft::$app->getRequest()->getAbsoluteUrl();

        // Remove the query string if unique query strings should be cached as the same page
        if (Blitz::$plugin->settings->queryStringCaching == 2) {
            $url = preg_replace('/\?.*/', '', $url);
        }

        $siteUri = SiteUriHelper::getSiteUriFromUrl($url);

        return $siteUri;
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

        return ($request->getIsSiteRequest()
            && $request->getIsGet()
            && !$request->getIsConsoleRequest()
            && !$request->getIsActionRequest()
            && !$request->getIsPreview()
        );
    }
}
