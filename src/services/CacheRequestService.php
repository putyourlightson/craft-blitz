<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\services;

use Craft;
use craft\base\Component;
use craft\db\Query;
use craft\db\Table;
use craft\elements\User;
use craft\events\CancelableEvent;
use craft\helpers\Json;
use craft\web\Application;
use craft\web\Request;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\drivers\generators\BaseCacheGenerator;
use putyourlightson\blitz\drivers\storage\BaseCacheStorage;
use putyourlightson\blitz\enums\HeaderEnum;
use putyourlightson\blitz\events\ResponseEvent;
use putyourlightson\blitz\helpers\SiteUriHelper;
use putyourlightson\blitz\models\SettingsModel;
use putyourlightson\blitz\models\SiteUriModel;
use putyourlightson\blitz\records\IncludeRecord;
use yii\web\Response;

/**
 * @property-read bool $isCacheableRequest
 * @property-read null|SiteUriModel $requestedCacheableSiteUri
 * @property-read string $allowedQueryString
 * @property-read string[] $acceptedRequestEncodings
 */
class CacheRequestService extends Component
{
    /**
     * @const CancelableEvent
     */
    public const EVENT_IS_CACHEABLE_REQUEST = 'isCacheableRequest';

    /**
     * @const ResponseEvent
     */
    public const EVENT_BEFORE_GET_RESPONSE = 'beforeGetResponse';

    /**
     * @const ResponseEvent
     */
    public const EVENT_AFTER_GET_RESPONSE = 'afterGetResponse';

    /**
     * @const string
     */
    public const CACHED_INCLUDE_ACTION = 'blitz/include/cached';

    /**
     * @const string
     */
    public const DYNAMIC_INCLUDE_ACTION = 'blitz/include/dynamic';

    /**
     * @const string
     */
    public const CACHED_INCLUDE_PATH = '_includes';

    /**
     * @const string
     */
    public const DYNAMIC_INCLUDE_PATH = '_dynamic';

    /**
     * @var bool|null
     */
    private ?bool $isGeneratorRequest = null;

    /**
     * @var array|null
     */
    private ?array $allowedQueryStrings = [];

    /**
     * Sets the default cache control header.
     */
    public function setDefaultCacheControlHeader(): void
    {
        if (Blitz::$plugin->settings->defaultCacheControlHeader !== null) {
            Craft::$app->getResponse()->getHeaders()->set(HeaderEnum::CACHE_CONTROL, Blitz::$plugin->settings->defaultCacheControlHeader);
        }
    }

    /**
     * Returns whether the request is cacheable.
     */
    public function getIsCacheableRequest(): bool
    {
        if ($this->getIsCachedInclude()) {
            return true;
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

        $url = $request->getAbsoluteUrl();

        /** @var User|null $user */
        $user = Craft::$app->getUser()->getIdentity();

        if ($user !== null) {
            // Ensure that if the site is not live that the user has permission to access it
            if (!Craft::$app->getIsLive() && !$user->can('accessSiteWhenSystemIsOff')) {
                Blitz::$plugin->debug('Page not cached because the site is not live and the user does not have permission to access it.', [], $url);

                return false;
            }

            // Ensure that the debug toolbar is not enabled
            if ($user->getPreference('enableDebugToolbarForSite')) {
                Blitz::$plugin->debug('Page not cached because the debug toolbar is enabled.', [], $url);

                return false;
            }
        }

        if (!empty($request->getParam('no-cache'))) {
            Blitz::$plugin->debug('Page not cached because a `no-cache` request parameter was provided.', [], $url);

            return false;
        }

        if ($request->getToken() !== null && !$this->getIsGeneratorRequest()) {
            Blitz::$plugin->debug('Page not cached because a token request header/parameter was provided.', [], $url);

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
     * Returns whether the response is cacheable.
     */
    public function getIsCacheableResponse(Response $response): bool
    {
        if ($response->content === null) {
            return false;
        }

        if ($this->getIsCachedInclude()) {
            return true;
        }

        if ($this->getIsDynamicInclude()) {
            return false;
        }

        return $response->format == Response::FORMAT_HTML
            || $response->format == 'template'
            || Blitz::$plugin->settings->cacheNonHtmlResponses;
    }

    /**
     * Returns whether the site URI is cacheable.
     */
    public function getIsCacheableSiteUri(SiteUriModel $siteUri): bool
    {
        $uri = strtolower($siteUri->uri);

        // Ignore URIs that are longer than the max URI length
        $max = Blitz::$plugin->settings->maxUriLength;
        if (strlen($uri) > $max) {
            Blitz::$plugin->debug('Page not cached because it exceeds the max URI length of {max}.', ['max' => $max], $uri);

            return false;
        }

        if ($this->getIsCachedInclude($uri)) {
            return true;
        }

        if ($this->getIsDynamicInclude($uri)) {
            return false;
        }

        $url = $siteUri->getUrl();
        $generalConfig = Craft::$app->getConfig()->getGeneral();

        // Ignore URIs that are resources
        $resourceBaseUri = trim(parse_url(Craft::getAlias($generalConfig->resourceBaseUrl), PHP_URL_PATH), '/');

        if ($resourceBaseUri && str_starts_with($uri, $resourceBaseUri)) {
            return false;
        }

        // Ignore URIs that contain `index.php`
        if (str_contains($uri, 'index.php')) {
            Blitz::$plugin->debug('Page not cached because the URL contains `index.php`.', [], $url);

            return false;
        }

        // Excluded URI patterns take priority
        if ($this->matchesUriPatterns($siteUri, Blitz::$plugin->settings->excludedUriPatterns)) {
            Blitz::$plugin->debug('Page not cached because it matches an excluded URI pattern.', [], $url);

            return false;
        }

        if (!$this->matchesUriPatterns($siteUri, Blitz::$plugin->settings->includedUriPatterns)) {
            Blitz::$plugin->debug('Page not cached because it does not match an included URI pattern.', [], $url);

            return false;
        }

        if (Blitz::$plugin->settings->queryStringCaching == SettingsModel::QUERY_STRINGS_DO_NOT_CACHE_URLS) {
            $allowedQueryString = $this->getAllowedQueryString($siteUri->siteId, $siteUri->uri);

            if ($allowedQueryString) {
                Blitz::$plugin->debug('Page not cached because a query string was provided with the query string caching setting disabled.', [], $url);

                return false;
            }
        }

        // Ignore URLs that don't start with `http`
        if (!str_starts_with(strtolower($url), 'http')) {
            Blitz::$plugin->debug('Page not cached because the URL does not start with `http`.', [], $url);

            return false;
        }

        return true;
    }

    /**
     * Returns whether the site URI is expired.
     *
     * @since 4.8.0
     */
    public function getIsExpiredSiteUri(SiteUriModel $siteUri): bool
    {
        return Blitz::$plugin->expireCache->getExpiredCacheId($siteUri) !== false;
    }

    /**
     * Returns whether this is a cached include.
     * Doesn’t memoize the result, which would disrupt the local cache generator.
     *
     * @since 4.3.0
     */
    public function getIsCachedInclude(string $uri = null): bool
    {
        // Includes based on the provided URI takes preference
        if ($uri !== null) {
            $uri = trim($uri, '/');

            return str_starts_with($uri, self::CACHED_INCLUDE_PATH);
        }

        if (Craft::$app->getRequest()->getIsActionRequest()) {
            $action = implode('/', Craft::$app->getRequest()->getActionSegments());

            return $action == self::CACHED_INCLUDE_ACTION;
        }

        $uri = Craft::$app->getRequest()->getFullUri();

        return str_starts_with($uri, self::CACHED_INCLUDE_PATH);
    }

    /**
     * Returns whether this is a dynamic include.
     * Doesn’t memoize the result, which would disrupt the local cache generator.
     *
     * @since 4.6.0
     */
    public function getIsDynamicInclude(string $uri = null): bool
    {
        // Includes based on the URI takes preference
        if ($uri !== null) {
            $uri = trim($uri, '/');

            return str_starts_with($uri, self::DYNAMIC_INCLUDE_PATH);
        }

        if (Craft::$app->getRequest()->getIsActionRequest()) {
            $action = implode('/', Craft::$app->getRequest()->getActionSegments());

            return $action == self::DYNAMIC_INCLUDE_ACTION;
        }

        return false;
    }

    /**
     * Returns whether this is a generator request.
     *
     * @since 4.0.0
     */
    public function getIsGeneratorRequest(): bool
    {
        if ($this->isGeneratorRequest !== null) {
            return $this->isGeneratorRequest;
        }

        $token = Craft::$app->getRequest()->getToken();

        if ($token == null) {
            $this->isGeneratorRequest = false;
        } else {
            // Don't use Tokens::getTokenRoute, as that can result in the token being deleted.
            // https://github.com/putyourlightson/craft-blitz/issues/448
            $route = (new Query())
                ->select('route')
                ->from(Table::TOKENS)
                ->where(['token' => $token])
                ->column();
            $route = (array)Json::decodeIfJson($route);
            $this->isGeneratorRequest = in_array(BaseCacheGenerator::GENERATE_ACTION_ROUTE, $route);
        }

        return $this->isGeneratorRequest;
    }

    /**
     * Returns an include record by index.
     *
     * @since 4.3.0
     */
    public function getIncludeByIndex(?int $index): ?IncludeRecord
    {
        if ($index === null) {
            return null;
        }

        /** @var IncludeRecord|null $include */
        $include = IncludeRecord::find()
            ->where(['index' => $index])
            ->one();

        return $include;
    }

    /**
     * Returns the cacheable requested site URI taking the query string into account.
     */
    public function getRequestedCacheableSiteUri(): ?SiteUriModel
    {
        if ($this->getIsCachedInclude()) {
            $index = Craft::$app->getRequest()->getParam('index');
            $include = $this->getIncludeByIndex($index);

            if ($include === null) {
                return null;
            }

            $queryParams = [
                'action' => Craft::$app->getRequest()->getParam('action'),
                'index' => $index,
            ];

            return new SiteUriModel([
                'siteId' => $include->siteId,
                'uri' => self::CACHED_INCLUDE_PATH . '?' . http_build_query($queryParams),
            ]);
        }

        $site = Craft::$app->getSites()->getCurrentSite();
        $uri = Craft::$app->getRequest()->getFullUri();

        /**
         * Build the query string from the query params, so that [[Request::getQueryString()]]
         * doesn’t get called, which is determined from the `$_SERVER` global variable
         * and which breaks our Pest tests.
         *
         * @see Request::getQueryString()
         */
        $queryString = http_build_query(Craft::$app->getRequest()->getQueryParams());

        /**
         * Remove the base site path from the full URI
         *
         * @see Request::init()
         */
        $baseSitePath = parse_url($site->getBaseUrl(), PHP_URL_PATH);

        if ($baseSitePath !== null) {
            $baseSitePath = $this->normalizePath($baseSitePath);

            if (str_starts_with($uri . '/', $baseSitePath . '/')) {
                $uri = ltrim(substr($uri, strlen($baseSitePath)), '/');
            }
        }

        // Add the allowed query string if unique query strings should not be cached as the same page
        if (Blitz::$plugin->settings->queryStringCaching != SettingsModel::QUERY_STRINGS_CACHE_URLS_AS_SAME_PAGE) {
            $allowedQueryString = $this->getAllowedQueryString($site->id, '?' . $queryString);

            if ($allowedQueryString) {
                $uri .= '?' . $allowedQueryString;
            }
        }

        return new SiteUriModel([
            'siteId' => Craft::$app->getSites()->getCurrentSite()->id,
            'uri' => $uri,
        ]);
    }

    /**
     * Returns the cached response of a site URI.
     */
    public function getCachedResponse(SiteUriModel $siteUri): ?Response
    {
        if ($this->getIsGeneratorRequest()) {
            return null;
        }

        $response = Craft::$app->getResponse();

        $event = new ResponseEvent([
            'siteUri' => $siteUri,
            'response' => $response,
        ]);
        $this->trigger(self::EVENT_BEFORE_GET_RESPONSE, $event);

        if (!$event->isValid) {
            return null;
        }

        $cacheStorage = Blitz::$plugin->cacheStorage;
        $siteUri = $event->siteUri;
        $encoded = $this->requestAcceptsEncoding();
        $content = '';

        if ($encoded && $cacheStorage->canCompressCachedValues()) {
            $content = $cacheStorage->getCompressed($siteUri);
        }

        // Fall back to unencoded, in case of cached includes or SSI includes
        if (empty($content)) {
            $encoded = false;
            $content = $cacheStorage->get($siteUri);
        }

        if (empty($content)) {
            return null;
        }

        $response = $event->response;
        $this->addCraftHeaders($response);
        $this->prepareResponse($response, $siteUri, $content, $encoded);
        $this->appendServedByComment($response, $siteUri, $encoded);

        if ($this->hasEventHandlers(self::EVENT_AFTER_GET_RESPONSE)) {
            $this->trigger(self::EVENT_AFTER_GET_RESPONSE, $event);
        }

        return $response;
    }

    /**
     * Saves and prepares the response for a given site URI.
     *
     * @since 3.12.0
     */
    public function saveAndPrepareResponse(?Response $response, SiteUriModel $siteUri): void
    {
        if ($response === null || $response->content === null) {
            return;
        }

        if ($response->getIsOk() === false) {
            return;
        }

        if ($this->getIsCacheableResponse($response) === false) {
            return;
        }

        // Save the content and prepare the response
        $content = Blitz::$plugin->generateCache->save($response->content, $siteUri);

        if ($content) {
            $this->prepareResponse($response, $siteUri, $content);
        }
    }

    /**
     * Returns true if the URI matches a set of patterns.
     */
    public function matchesUriPatterns(SiteUriModel $siteUri, array|string $siteUriPatterns): bool
    {
        if (!is_array($siteUriPatterns)) {
            return false;
        }

        foreach ($siteUriPatterns as $siteUriPattern) {
            if (empty($siteUriPattern['siteId']) || $siteUriPattern['siteId'] == $siteUri->siteId) {
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

                if (preg_match('/' . $uriPattern . '/', trim($siteUri->uri, '/'))) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Returns true if the site and parameter match a set of query string parameters.
     */
    public function matchesQueryStringParams(int $siteId, string $param, array|string $queryStringParams): bool
    {
        if (!is_array($queryStringParams)) {
            return false;
        }

        foreach ($queryStringParams as $queryStringParam) {
            if (empty($queryStringParam['siteId']) || $queryStringParam['siteId'] == $siteId) {
                if (preg_match('/' . $queryStringParam['queryStringParam'] . '/', $param)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Returns the query string after processing the included and excluded query string params.
     */
    public function getAllowedQueryString(int $siteId, string $uri): string
    {
        if (!empty($this->allowedQueryStrings[$siteId][$uri])) {
            return $this->allowedQueryStrings[$siteId][$uri];
        }

        $queryString = parse_url($uri, PHP_URL_QUERY) ?: '';
        parse_str($queryString, $queryStringParams);

        foreach ($queryStringParams as $key => $value) {
            if (!$this->getIsAllowedQueryStringParam($siteId, $key)) {
                unset($queryStringParams[$key]);
            }
        }

        $this->allowedQueryStrings[$siteId][$uri] = http_build_query($queryStringParams);

        return $this->allowedQueryStrings[$siteId][$uri];
    }

    /**
     * Returns whether the query string parameter is allowed.
     */
    public function getIsAllowedQueryStringParam(int $siteId, string $param): bool
    {
        $generalConfig = Craft::$app->getConfig()->getGeneral();
        if ($param == $generalConfig->pathParam || $param == $generalConfig->tokenParam) {
            return false;
        }

        $excludedQueryStringParams = is_array(Blitz::$plugin->settings->excludedQueryStringParams) ? Blitz::$plugin->settings->excludedQueryStringParams : [];
        if ($this->matchesQueryStringParams($siteId, $param, $excludedQueryStringParams)) {
            return false;
        }

        $includedQueryStringParams = is_array(Blitz::$plugin->settings->includedQueryStringParams) ? Blitz::$plugin->settings->includedQueryStringParams : [];
        if ($this->matchesQueryStringParams($siteId, $param, $includedQueryStringParams)) {
            return true;
        }

        return false;
    }

    /**
     * Returns whether the request accepts encoding.
     * https://developer.mozilla.org/en-US/docs/Glossary/Quality_values.
     */
    public function requestAcceptsEncoding(): bool
    {
        $encoding = Craft::$app->getRequest()->getHeaders()->get(HeaderEnum::ACCEPT_ENCODING);

        if (empty($encoding)) {
            return false;
        }

        $encodings = Craft::$app->getRequest()->parseAcceptHeader($encoding);

        return isset($encodings[BaseCacheStorage::ENCODING]);
    }

    /**
     * Adds headers that Craft normally would.
     *
     * @see Application::handleRequest()
     * @since 3.12.0
     */
    private function addCraftHeaders(Response $response): void
    {
        $headers = $response->getHeaders();
        $generalConfig = Craft::$app->getConfig()->getGeneral();

        if ($generalConfig->permissionsPolicyHeader) {
            $headers->set(HeaderEnum::PERMISSIONS_POLICY, $generalConfig->permissionsPolicyHeader);
        }

        // Tell bots not to index/follow CP and tokenized pages
        if ($generalConfig->disallowRobots) {
            $headers->set(HeaderEnum::X_ROBOTS_TAG, 'none');
        }

        // Send or remove the powered by header
        if ($generalConfig->sendPoweredByHeader) {
            $poweredByHeader = $headers->get(HeaderEnum::X_POWERED_BY, first: false);

            if (!in_array(Craft::$app->name, $poweredByHeader)) {
                $headers->add(HeaderEnum::X_POWERED_BY, Craft::$app->name);
            }
        } else {
            $headers->remove(HeaderEnum::X_POWERED_BY);

            // In case PHP is already setting one
            header_remove(HeaderEnum::X_POWERED_BY);
        }
    }

    /**
     * Prepares the response for a given site URI.
     *
     * @since 3.12.0
     */
    private function prepareResponse(Response $response, SiteUriModel $siteUri, string $content, bool $encoded = false): void
    {
        $response->content = $content;

        $cacheControlHeader = Blitz::$plugin->settings->cacheControlHeader;

        if ($this->getIsExpiredSiteUri($siteUri)) {
            $cacheControlHeader = Blitz::$plugin->settings->cacheControlHeaderExpired;
            Blitz::$plugin->refreshCache->refreshExpiredSiteUri($siteUri);
        }

        $headers = $response->getHeaders();
        $headers->set(HeaderEnum::CACHE_CONTROL, $cacheControlHeader);

        if ($encoded) {
            $headers->set(HeaderEnum::CONTENT_ENCODING, BaseCacheStorage::ENCODING);
        }

        if (Blitz::$plugin->settings->sendPoweredByHeader) {
            $headers->add(HeaderEnum::X_POWERED_BY, 'Blitz');
        }

        // Add cache tag header if set
        $tags = Blitz::$plugin->cacheTags->getSiteUriTags($siteUri);
        if (!empty($tags)) {
            $tagHeaderName = Blitz::$plugin->cachePurger->tagHeaderName;
            if ($tagHeaderName) {
                $tagsHeader = implode(Blitz::$plugin->cachePurger->tagHeaderDelimiter, $tags);
                $headers->set($tagHeaderName, $tagsHeader);
            }
        }

        // Add headers if ESI is enabled for pages only
        if (Blitz::$plugin->settings->esiEnabled && $this->getIsCachedInclude() === false) {
            $headers->add('Surrogate-Control', 'content="ESI/1.0"');
        }

        // Remove cookies as the `Set-Cookie` header can prevent edge-side caching.
        // https://developers.cloudflare.com/cache/about/default-cache-behavior
        $response->getCookies()->removeAll();

        // Get the mime type from the site URI
        $mimeType = SiteUriHelper::getMimeType($siteUri);

        if ($mimeType != SiteUriHelper::MIME_TYPE_HTML) {
            $headers->set(HeaderEnum::CONTENT_TYPE, $mimeType);

            if ($response->format == Response::FORMAT_HTML) {
                $response->format = Response::FORMAT_RAW;
            }
        }
    }

    /**
     * Appends the served by comment to the response content.
     */
    private function appendServedByComment(Response $response, SiteUriModel $siteUri, bool $encoded): void
    {
        // Appending onto encoded content is not possible
        if ($encoded) {
            return;
        }

        if ($this->getIsCachedInclude()
            || !$this->getIsCacheableResponse($response)
            || !SiteUriHelper::hasHtmlMimeType($siteUri)
        ) {
            return;
        }

        $outputComments = Blitz::$plugin->generateCache->options->outputComments;

        if ($outputComments === null) {
            $outputComments = Blitz::$plugin->settings->outputComments;
        }

        if ($outputComments !== true && $outputComments !== SettingsModel::OUTPUT_COMMENTS_SERVED) {
            return;
        }

        $response->content .= '<!-- Served by Blitz on ' . date('c') . ' -->';
    }

    /**
     * Normalizes a URI path by trimming leading/trailing slashes and removing double slashes.
     *
     * @see Request::_normalizePath()
     * @since 3.10.6
     */
    private function normalizePath(string $path): string
    {
        return preg_replace('/\/\/+/', '/', trim($path, '/'));
    }
}
