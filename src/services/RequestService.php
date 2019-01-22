<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\services;

use Craft;
use craft\helpers\UrlHelper;
use putyourlightson\blitz\Blitz;
use yii\base\Exception;

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

        if (!Blitz::$settings->cachingEnabled) {
            return false;
        }

        if (Blitz::$settings->queryStringCaching == 0 && $request->getQueryStringWithoutPath() !== '') {
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
    public function getIsCacheableUri(int $siteId, string $uri): bool
    {
        // Ignore URIs that contain index.php
        if (strpos($uri, 'index.php') !== false) {
            return false;
        }

        // Excluded URI patterns take priority
        if (is_array(Blitz::$settings->excludedUriPatterns)) {
            if (self::matchesUriPattern(Blitz::$settings->excludedUriPatterns, $siteId, $uri)) {
                return false;
            }
        }

        if (is_array(Blitz::$settings->includedUriPatterns)) {
            if (self::matchesUriPattern(Blitz::$settings->includedUriPatterns, $siteId, $uri)) {
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
    public function getSiteUrl(int $siteId, string $uri): string
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
    public function matchesUriPattern(array $patterns, int $siteId, string $uri): bool
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

    /**
     * Gets the requested URI.
     *
     * @return string
     */
    public function getCurrentUri(): string
    {
        $site = Craft::$app->getSites()->getCurrentSite();
        $uri = Craft::$app->getRequest()->getAbsoluteUrl();

        // Remove the query string if unique query strings should be cached as the same page
        if (Blitz::$settings->queryStringCaching == 2) {
            $uri = preg_replace('/\?.*/', '', $uri);
        }

        // Remove site base URL
        $baseUrl = trim(Craft::getAlias($site->baseUrl), '/');
        $uri = str_replace($baseUrl, '', $uri);

        // Trim slashes from the beginning and end of the URI
        $uri = trim($uri, '/');

        return $uri;
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

        if (Blitz::$settings->sendPoweredByHeader) {
            $header = Craft::$app->getConfig()->getGeneral()->sendPoweredByHeader ? 'Craft CMS, ' : '';
            header('X-Powered-By: '.$header.'Blitz');
        }

        // Update cache control header
        header('Cache-Control: '.Blitz::$settings->cacheControlHeader);

        exit($value.'<!-- Served by Blitz -->');
    }
}