<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\helpers;

use Craft;
use craft\helpers\UrlHelper;
use putyourlightson\blitz\Blitz;
use yii\base\Exception;

class CacheHelper
{
    // Static
    // =========================================================================

    /**
     * Returns whether the request is cacheable.
     *
     * @return bool
     */
    public static function getIsCacheableRequest(): bool
    {
        $settings = Blitz::$plugin->getSettings();

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

        if (!$settings->cachingEnabled) {
            return false;
        }

        if ($settings->queryStringCaching == 0 && $request->getQueryStringWithoutPath() !== '') {
            return false;
        }

        return true;
    }

    /**
     * Returns whether the URI is cacheable.
     *
     * @param int $siteId
     * @param string $uri
     *
     * @return bool
     */
    public static function getIsCacheableUri(int $siteId, string $uri): bool
    {
        $settings = Blitz::$plugin->getSettings();

        // Ignore URIs that contain index.php
        if (strpos($uri, 'index.php') !== false) {
            return false;
        }

        // Excluded URI patterns take priority
        if (is_array($settings->excludedUriPatterns)) {
            if (self::matchesUriPattern($settings->excludedUriPatterns, $siteId, $uri)) {
                return false;
            }
        }

        if (is_array($settings->includedUriPatterns)) {
            if (self::matchesUriPattern($settings->includedUriPatterns, $siteId, $uri)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns a URL given a site ID and URI.
     *
     * @param int $siteId
     * @param string $uri
     *
     * @return string
     * @throws Exception
     */
    public static function getSiteUrl(int $siteId, string $uri): string
    {
        return UrlHelper::siteUrl($uri, null, null, $siteId);
    }

    /**
     * Matches a URI pattern in a set of patterns.
     *
     * @param array $patterns
     * @param int $siteId
     * @param string $uri
     *
     * @return bool
     */
    public static function matchesUriPattern(array $patterns, int $siteId, string $uri): bool
    {
        foreach ($patterns as $pattern) {
            // Don't proceed if site is not empty and does not match the provided site ID
            if (!empty($pattern[1]) && $pattern[1] != $siteId) {
                continue;
            }

            $uriPattern = $pattern[0];

            // Replace a blank string with the homepage
            if ($uriPattern == '') {
                $uriPattern = '^$';
            }

            // Replace "*" with 0 or more characters as otherwise it'll throw an error
            if ($uriPattern == '*') {
                $uriPattern = '.*';
            }

            // Trim slashes
            $uriPattern = trim($uriPattern, '/');

            // Escape hash symbols
            $uriPattern = str_replace('#', '\#', $uriPattern);

            if (preg_match('#'.$uriPattern.'#', trim($uri, '/'))) {
                return true;
            }
        }

        return false;
    }
}