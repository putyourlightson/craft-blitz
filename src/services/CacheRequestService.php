<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\services;

use Craft;
use craft\base\Component;
use craft\elements\User;
use craft\events\CancelableEvent;
use craft\web\Request;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\events\ResponseEvent;
use putyourlightson\blitz\helpers\SiteUriHelper;
use putyourlightson\blitz\models\SettingsModel;
use putyourlightson\blitz\models\SiteUriModel;
use yii\web\Response;

/**
 *
 * @property-read bool $isCacheableRequest
 * @property-read null|SiteUriModel $requestedCacheableSiteUri
 * @property-read string $allowedQueryString
 */
class CacheRequestService extends Component
{
    /**
     * @const CancelableEvent
     */
    const EVENT_IS_CACHEABLE_REQUEST = 'isCacheableRequest';

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

    /**
     * @var string|null
     */
    private $_queryString;

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

        if (!empty($request->getParam(Craft::$app->config->general->tokenParam))) {
            Blitz::$plugin->debug('Page not cached because a token request parameter was provided.', [], $request->getAbsoluteUrl());

            return false;
        }

        // Check for path param in URL because `$request->getQueryString()` will contain it regardless
        if (preg_match('/[?&]'.Craft::$app->config->general->pathParam.'=/', $request->getUrl()) === 1) {
            Blitz::$plugin->debug('Page not cached because a path param was provided in the query string. ', [], $request->getAbsoluteUrl());

            return false;
        }

        if (Blitz::$plugin->settings->queryStringCaching == SettingsModel::QUERY_STRINGS_DO_NOT_CACHE_URLS
            && !empty($this->getAllowedQueryString())
        ) {
            Blitz::$plugin->debug('Page not cached because a query string was provided with the query string caching setting disabled.', [], $request->getAbsoluteUrl());

            return false;
        }

        $event = new CancelableEvent();
        $this->trigger(self::EVENT_IS_CACHEABLE_REQUEST, $event);

        if (!$event->isValid) {
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
        $site = Craft::$app->getSites()->getCurrentSite();
        $uri = $this->_getFullUri();

        /**
         * Remove the base site path from the full URI
         * @see Request::init()
         */
        $baseSitePath = parse_url($site->getBaseUrl(), PHP_URL_PATH);

        if ($baseSitePath !== null) {
            $baseSitePath = $this->_normalizePath($baseSitePath);

            if (strpos($uri . '/', $baseSitePath . '/') === 0) {
                $uri = ltrim(substr($uri, strlen($baseSitePath)), '/');
            }
        }

        // Add the allowed query string if unique query strings should not be cached as the same page
        if (Blitz::$plugin->settings->queryStringCaching != SettingsModel::QUERY_STRINGS_CACHE_URLS_AS_SAME_PAGE
            && !empty($this->getAllowedQueryString())
        ) {
            $uri .= '?'.$this->getAllowedQueryString();
        }

        return new SiteUriModel([
            'siteId' => Craft::$app->getSites()->getCurrentSite()->id,
            'uri' => $uri,
        ]);
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
        // Ignore URIs that are CP pages
        $generalConfig = Craft::$app->getConfig()->getGeneral();

        if ($generalConfig->cpTrigger && strpos($siteUri->uri, $generalConfig->cpTrigger) !== false) {
            return false;
        }

        // Ignore URIs that are resources
        $resourceBaseUri = trim(parse_url(Craft::getAlias($generalConfig->resourceBaseUrl), PHP_URL_PATH), '/');

        if ($resourceBaseUri && strpos($siteUri->uri, $resourceBaseUri) !== false) {
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
        if ($this->matchesUriPatterns($siteUri, Blitz::$plugin->settings->excludedUriPatterns)) {
            Blitz::$plugin->debug('Page not cached because it matches an excluded URI pattern.', [], $siteUri->getUrl());

            return false;
        }

        if (!$this->matchesUriPatterns($siteUri, Blitz::$plugin->settings->includedUriPatterns)) {
            Blitz::$plugin->debug('Page not cached because it does not match an included URI pattern.', [], $siteUri->getUrl());

            return false;
        }

        return true;
    }

    /**
     * Returns the response of a given site URI, if cached.
     *
     * @param SiteUriModel $siteUri
     * @return Response|null
     */
    public function getResponse(SiteUriModel $siteUri)
    {
        /** @var Response $response */
        $response = Craft::$app->getResponse();

        $event = new ResponseEvent([
            'siteUri' => $siteUri,
            'response' => $response,
        ]);
        $this->trigger(self::EVENT_BEFORE_GET_RESPONSE, $event);

        if (!$event->isValid) {
            return null;
        }

        $siteUri = $event->siteUri;
        $content = Blitz::$plugin->cacheStorage->get($siteUri);

        if (empty($content)) {
            return null;
        }

        $response = $event->response;
        $this->_addCraftHeaders($response);
        $this->prepareResponse($response, $content, $siteUri);

        // Append the served by comment if this is an HTML response and if allowed.
        if ($response->format == Response::FORMAT_HTML && SiteUriHelper::hasHtmlMimeType($siteUri)) {
            $outputComments = Blitz::$plugin->settings->outputComments;

            if ($outputComments === true || $outputComments == SettingsModel::OUTPUT_COMMENTS_SERVED) {
                $content .= '<!-- Served by Blitz on ' . date('c') . ' -->';
            }
        }

        $response->content = $content;

        if ($this->hasEventHandlers(self::EVENT_AFTER_GET_RESPONSE)) {
            $this->trigger(self::EVENT_AFTER_GET_RESPONSE, $event);
        }

        return $response;
    }

    /**
     * Prepares the response for a given site URI.
     *
     * @since 3.12.0
     */
    public function prepareResponse(Response $response, string $content, SiteUriModel $siteUri)
    {
        $response->content = $content;

        $headers = $response->getHeaders();
        $headers->set('Cache-Control', Blitz::$plugin->settings->cacheControlHeader);

        if (Blitz::$plugin->settings->sendPoweredByHeader) {
            $original = $headers->get('X-Powered-By');
            $headers->set('X-Powered-By', $original . ($original ? ',' : '') . 'Blitz');
        }

        // Add cache tag header if set
        $tags = Blitz::$plugin->cacheTags->getSiteUriTags($siteUri);

        if (!empty($tags) && Blitz::$plugin->cachePurger->tagHeaderName) {
            $tagsHeader = implode(Blitz::$plugin->cachePurger->tagHeaderDelimiter, $tags);
            $headers->set(Blitz::$plugin->cachePurger->tagHeaderName, $tagsHeader);
        }

        // Get the mime type from the site URI
        $mimeType = SiteUriHelper::getMimeType($siteUri);

        if ($mimeType != SiteUriHelper::MIME_TYPE_HTML) {
            $response->format = Response::FORMAT_RAW;
            $headers->set('Content-Type', $mimeType);
        }
    }

    /**
     * Returns true if the URI matches a set of patterns.
     *
     * @param SiteUriModel $siteUri
     * @param array|string $siteUriPatterns
     *
     * @return bool
     */
    public function matchesUriPatterns(SiteUriModel $siteUri, $siteUriPatterns): bool
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

            // Escape delimiters, removing already escaped delimiters first
            // https://github.com/putyourlightson/craft-blitz/issues/261
            $uriPattern = str_replace(['\/', '/'], ['/', '\/'], $uriPattern);

            if (preg_match('/'.$uriPattern.'/', trim($siteUri->uri, '/'))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns the query string after processing the included and excluded query string params.
     *
     * @return string
     */
    public function getAllowedQueryString(): string
    {
        if ($this->_queryString !== null) {
            return $this->_queryString;
        }

        $queryString = Craft::$app->getRequest()->getQueryStringWithoutPath();
        $queryStringParams = explode('&', $queryString);

        foreach ($queryStringParams as $key => $queryStringParam) {
            $param = explode('=', $queryStringParam);

            if (!$this->getIsAllowedQueryStringParam($param[0])) {
                unset($queryStringParams[$key]);
            }
        }

        $this->_queryString = implode('&', $queryStringParams);

        return $this->_queryString;
    }

    /**
     * Returns whether the query string parameter is allowed.
     *
     * @param string $param
     *
     * @return bool
     */
    public function getIsAllowedQueryStringParam(string $param): bool
    {
        foreach (Blitz::$plugin->settings->excludedQueryStringParams as $excludedParam) {
            if (preg_match('/'.$excludedParam.'/', $param)) {
                return false;
            }
        }

        foreach (Blitz::$plugin->settings->includedQueryStringParams as $includedParam) {
            if (preg_match('/'.$includedParam.'/', $param)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns the full requested URI.
     * TODO: replace with Request::getFullUri() in version 4.0.0.
     *
     * @see Request::getFullUri()
     * @since 3.10.6
     */
    private function _getFullUri(): string
    {
        $baseUrl = $this->_normalizePath(Craft::$app->getRequest()->getBaseUrl());
        $path = Craft::$app->getRequest()->getFullPath();

        return $baseUrl . ($baseUrl && $path ? '/' : '') . $path;
    }

    /**
     * Adds headers that Craft normally would.
     *
     * @param Response $response
     *
     * @see Application::handleRequest()
     * @since 3.12.0
     */
    private function _addCraftHeaders(Response $response)
    {
        $headers = $response->getHeaders();
        $generalConfig = Craft::$app->getConfig()->getGeneral();

        if ($generalConfig->permissionsPolicyHeader) {
            $headers->set('Permissions-Policy', $generalConfig->permissionsPolicyHeader);
        }

        // Tell bots not to index/follow CP and tokenized pages
        if ($generalConfig->disallowRobots) {
            $headers->set('X-Robots-Tag', 'none');
        }

        // Send the X-Powered-By header?
        if ($generalConfig->sendPoweredByHeader) {
            $original = $headers->get('X-Powered-By');
            $headers->set('X-Powered-By', $original . ($original ? ',' : '') . Craft::$app->name);
        }
        else {
            // In case PHP is already setting one
            header_remove('X-Powered-By');
        }
    }

    /**
     * Normalizes a URI path by trimming leading/trailing slashes and removing double slashes.
     *
     * @see Request::_normalizePath()
     * @since 3.10.6
     */
    private function _normalizePath(string $path): string
    {
        return preg_replace('/\/\/+/', '/', trim($path, '/'));
    }
}
