<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\services;

use Craft;
use craft\base\Component;
use craft\elements\User;
use craft\web\Response;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\events\ResponseEvent;
use putyourlightson\blitz\helpers\SiteUriHelper;
use putyourlightson\blitz\models\SettingsModel;
use putyourlightson\blitz\models\SiteUriModel;

/**
 * @property bool $isCacheableRequest
 * @property SiteUriModel $requestedCacheableSiteUri
 */
class CacheRequestService extends Component
{
    // Constants
    // =========================================================================

    /**
     * @const ResponseEvent
     */
    const EVENT_BEFORE_GET_RESPONSE = 'beforeGetResponse';

    /**
     * @const ResponseEvent
     */
    const EVENT_AFTER_GET_RESPONSE = 'afterGetResponse';

    /**
     * @const int
     */
    const MAX_URI_LENGTH = 255;

    // Properties
    // =========================================================================

    /**
     * @var string|null
     */
    private $_queryString;

    // Public Methods
    // =========================================================================

    /**
     * Returns whether the request is cacheable.
     *
     * @return bool
     */
    public function getIsCacheableRequest(): bool
    {
        // Ensure caching is enabled
        if (!Blitz::$plugin->settings->cachingEnabled) {
            return false;
        }

        $request = Craft::$app->getRequest();

        // Ensure this is a cacheable site request
        if (!$request->getIsSiteRequest()
            || !$request->getIsGet()
            || $request->getIsConsoleRequest()
            || $request->getIsActionRequest()
            || $request->getIsPreview()
        ) {
            return false;
        }

        // Ensure the response is not an error
        if (!Craft::$app->getResponse()->getIsOk()) {
            return false;
        }

        /** @var User|null $user */
        $user = Craft::$app->getUser()->getIdentity();

        if ($user !== null) {
            // Ensure that if the site is not live that the user has permission to access it
            if (!Craft::$app->getIsLive() && !$user->can('accessSiteWhenSystemIsOff')) {
                Blitz::$plugin->debug('Page not cached because the site is not live and the user does not have permission to access it.', [], $request->getAbsoluteUrl());

                return false;
            }

            // Ensure that the debug toolbar is not enabled
            if ($user->getPreference('enableDebugToolbarForSite')) {
                Blitz::$plugin->debug('Page not cached because the debug toolbar is enabled.', [], $request->getAbsoluteUrl());

                return false;
            }
        }

        if (!empty($request->getParam('no-cache'))) {
            Blitz::$plugin->debug('Page not cached because a `no-cache` request parameter was provided.', [], $request->getAbsoluteUrl());

            return false;
        }

        if (!empty($request->getParam('token'))) {
            Blitz::$plugin->debug('Page not cached because a `token` request parameter was provided.', [], $request->getAbsoluteUrl());

            return false;
        }

        // Check for path param in URL because `$request->getQueryString()` will contain it regardless
        if (preg_match('/[?&]p=/', $request->getUrl()) === 1) {
            Blitz::$plugin->debug('Page not cached because a path param was provided in the query string. ', [], $request->getAbsoluteUrl());

            return false;
        }

        if (Blitz::$plugin->settings->queryStringCaching == SettingsModel::QUERY_STRINGS_DO_NOT_CACHE_URLS
            && !empty($this->_getQueryString())
        ) {
            Blitz::$plugin->debug('Page not cached because a query string was provided with the query string caching setting disabled.', [], $request->getAbsoluteUrl());

            return false;
        }

        return true;
    }

    /**
     * Returns the cacheable requested site URI taking the query string into account.
     *
     * @return SiteUriModel|null
     */
    public function getRequestedCacheableSiteUri()
    {
        $url = Craft::$app->getRequest()->getAbsoluteUrl();

        // Remove the query string
        $url = preg_replace('/\?.*/', '', $url);

        // Add the query string if unique query strings should not be cached as the same page
        if (Blitz::$plugin->settings->queryStringCaching != SettingsModel::QUERY_STRINGS_CACHE_URLS_AS_SAME_PAGE
            && !empty($this->_getQueryString()))
        {
            $url = $url.'?'.$this->_getQueryString();
        }

        $siteUri = SiteUriHelper::getSiteUriFromUrl($url);

        // Log a debug message if no site URI could be determined from the requested URL
        if ($siteUri === null) {
            Blitz::$plugin->debug('No site URI could be determined from the requested URL – ensure that the base site URL is correctly set.', [], $url);
        }

        return $siteUri;
    }

    /**
     * Returns whether the site URI is cacheable.
     *
     * @param SiteUriModel $siteUri
     *
     * @return bool
     */
    public function getIsCacheableSiteUri(SiteUriModel $siteUri): bool
    {
        // Ignore URIs that are CP pages or resources
        $generalConfig = Craft::$app->getConfig()->getGeneral();
        $resourceBaseUri = parse_url(Craft::getAlias($generalConfig->resourceBaseUrl), PHP_URL_PATH);

        if (strpos($siteUri->uri, $generalConfig->cpTrigger) !== false
            || strpos($siteUri->uri, trim($resourceBaseUri, '/')) !== false
        ) {
            return false;
        }

        // Ignore URIs that contain index.php
        if (strpos($siteUri->uri, 'index.php') !== false) {
            Blitz::$plugin->debug('Page not cached because the URL contains `index.php`.', [], $siteUri->getUrl());

            return false;
        }

        // Ignore URIs that are longer than the max URI length
        if (strlen($siteUri->uri) > self::MAX_URI_LENGTH) {
            Blitz::$plugin->debug('Page not cached because it exceeds the max URI length of {max} characters.', [
                'max' => self::MAX_URI_LENGTH
            ], $siteUri->getUrl());

            return false;
        }

        // Excluded URI patterns take priority
        if ($this->_matchesUriPatterns($siteUri, Blitz::$plugin->settings->excludedUriPatterns)) {
            Blitz::$plugin->debug('Page not cached because it matches an excluded URI pattern.', [], $siteUri->getUrl());

            return false;
        }

        if (!$this->_matchesUriPatterns($siteUri, Blitz::$plugin->settings->includedUriPatterns)) {
            Blitz::$plugin->debug('Page not cached because it does not match an included URI pattern.', [], $siteUri->getUrl());

            return false;
        }

        return true;
    }

    /**
     * Returns the response of a given site URI if cached.
     *
     * @param SiteUriModel $siteUri
     *
     * @return Response|null
     */
    public function getResponse(SiteUriModel $siteUri)
    {
        $value = Blitz::$plugin->cacheStorage->get($siteUri);

        if (empty($value)) {
            return null;
        }

        /** @var Response $response */
        $response = Craft::$app->getResponse();

        $event = new ResponseEvent([
            'response' => $response,
        ]);
        $this->trigger(self::EVENT_BEFORE_GET_RESPONSE, $event);

        if (!$event->isValid || !$value) {
            return null;
        }

        $headers = $response->getHeaders();

        $headers->set('Cache-Control', Blitz::$plugin->settings->cacheControlHeader);

        if (Blitz::$plugin->settings->sendPoweredByHeader) {
            $headers->add('X-Powered-By', Blitz::$plugin->name);
        }

        // Add cache tag header if set
        $tags = Blitz::$plugin->cacheTags->getSiteUriTags($siteUri);

        if (!empty($tags) && Blitz::$plugin->cachePurger->tagHeaderName) {
            $tagsHeader = implode(Blitz::$plugin->cachePurger->tagHeaderDelimiter, $tags);
            $headers->set(Blitz::$plugin->cachePurger->tagHeaderName, $tagsHeader);
        }

        // Get the mime type from the URI
        $mimeType = SiteUriHelper::getMimeType($siteUri);

        if ($mimeType != SiteUriHelper::MIME_TYPE_HTML) {
            $response->format = Response::FORMAT_RAW;
            $headers->set('Content-Type', $mimeType);
        }

        // Append served by comment if html and config setting is true
        if ($mimeType == SiteUriHelper::MIME_TYPE_HTML && Blitz::$plugin->settings->outputComments) {
            $value .= '<!-- Served by Blitz on '.date('c').' -->';
        }

        $response->data = $value;

        if ($this->hasEventHandlers(self::EVENT_AFTER_GET_RESPONSE)) {
            $this->trigger(self::EVENT_AFTER_GET_RESPONSE, $event);
        }

        return $response;
    }

    // Private Methods
    // =========================================================================

    /**
     * Returns true if the URI matches a set of patterns.
     *
     * @param SiteUriModel $siteUri
     * @param array|string $siteUriPatterns
     *
     * @return bool
     */
    private function _matchesUriPatterns(SiteUriModel $siteUri, $siteUriPatterns): bool
    {
        if (!is_array($siteUriPatterns)) {
            return false;
        }

        foreach ($siteUriPatterns as $siteUriPattern) {
            // Don't proceed if site is not empty and does not match the provided site ID
            if (!empty($siteUriPattern['siteId']) && $siteUriPattern['siteId'] != $siteUri->siteId) {
                continue;
            }

            $uriPattern = $siteUriPattern['uriPattern'];

            // Replace a blank string with the homepage with query strings allowed
            if ($uriPattern == '') {
                $uriPattern = '^(\?.*)?$';
            }

            // Replace "*" with 0 or more characters as otherwise it'll throw an error
            if ($uriPattern == '*') {
                $uriPattern = '.*';
            }

            // Trim slashes
            $uriPattern = trim($uriPattern, '/');

            // Escape hash symbols
            $uriPattern = str_replace('#', '\#', $uriPattern);

            if (preg_match('#'.$uriPattern.'#', trim($siteUri->uri, '/'))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns the query string without the excluded query string params.
     *
     * @return string
     */
    private function _getQueryString(): string
    {
        if ($this->_queryString !== null) {
            return $this->_queryString;
        }

        $queryStringParams = explode('&', Craft::$app->getRequest()->getQueryStringWithoutPath());

        foreach ($queryStringParams as $key => $queryStringParam) {
            foreach (Blitz::$plugin->settings->excludedQueryStringParams as $excludedParam) {
                if (strpos($queryStringParam, $excludedParam.'=') === 0) {
                    unset($queryStringParams[$key]);
                    break;
                }
            }
        }

        $this->_queryString = implode('&', $queryStringParams);

        return $this->_queryString;
    }
}
