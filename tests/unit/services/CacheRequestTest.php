<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitztests\unit\services;

use Codeception\Test\Unit;
use Craft;
use craft\helpers\App;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\models\SettingsModel;
use putyourlightson\blitz\models\SiteUriModel;
use UnitTester;

/**
 * @author    PutYourLightsOn
 * @package   Blitz
 * @since     3.0.0
 */

class CacheRequestTest extends Unit
{
    // Properties
    // =========================================================================

    /**
     * @var UnitTester
     */
    protected $tester;

    /**
     * @var SiteUriModel
     */
    private $siteUri;

    /**
     * @var array
     */
    private $uriPattern;

    // Protected methods
    // =========================================================================

    protected function _before()
    {
        parent::_before();

        $this->siteUri = new SiteUriModel([
            'siteId' => 1,
            'uri' => 'page',
        ]);

        $this->uriPattern = [
            'siteId' => $this->siteUri->siteId,
            'uriPattern' => $this->siteUri->uri,
        ];
    }

    // Public methods
    // =========================================================================

    public function testGetIsCacheableRequest()
    {
        // Mock a URL request
        $this->_mockRequest($this->siteUri->getUrl());

        // Assert that the request is not cacheable
        $this->assertFalse(Blitz::$plugin->cacheRequest->getIsCacheableRequest());

        // Enable caching and add an included URI pattern
        Blitz::$plugin->settings->cachingEnabled = true;

        // Hide the fact that this is a console request
        Craft::$app->getRequest()->isConsoleRequest = false;

        // Assert that the request is cacheable
        $this->assertTrue(Blitz::$plugin->cacheRequest->getIsCacheableRequest());
    }

    public function testGetIsCacheableRequestWithPathParam()
    {
        // Mock a URL request
        $this->_mockRequest($this->siteUri->getUrl().'?'.Craft::$app->config->general->pathParam.'=search');

        // Enable caching and add an included URI pattern
        Blitz::$plugin->settings->cachingEnabled = true;
        Blitz::$plugin->settings->includedUriPatterns = [$this->uriPattern];
        Blitz::$plugin->settings->queryStringCaching = SettingsModel::QUERY_STRINGS_CACHE_URLS_AS_UNIQUE_PAGES;

        // Hide the fact that this is a console request
        Craft::$app->getRequest()->isConsoleRequest = false;

        // Assert that the request is not cacheable
        $this->assertFalse(Blitz::$plugin->cacheRequest->getIsCacheableRequest());
    }

    public function testGetRequestedCacheableSiteUri()
    {
        $allowedQueryString = 'x=1&y=1';
        $disallowedQueryString = 'gclid=123';

        // Mock a URL request
        $this->_mockRequest($this->siteUri->getUrl().'?'.$disallowedQueryString.'&'.$allowedQueryString);

        // Enable caching and add an included URI pattern
        Blitz::$plugin->settings->cachingEnabled = true;
        Blitz::$plugin->settings->includedUriPatterns = [$this->uriPattern];

        Blitz::$plugin->settings->queryStringCaching = SettingsModel::QUERY_STRINGS_DO_NOT_CACHE_URLS;
        $uri = Blitz::$plugin->cacheRequest->getRequestedCacheableSiteUri()->uri;
        $this->assertEquals($this->siteUri->uri.'?'.$allowedQueryString, $uri);

        Blitz::$plugin->settings->queryStringCaching = SettingsModel::QUERY_STRINGS_CACHE_URLS_AS_UNIQUE_PAGES;
        $uri = Blitz::$plugin->cacheRequest->getRequestedCacheableSiteUri()->uri;
        $this->assertEquals($this->siteUri->uri.'?'.$allowedQueryString, $uri);

        Blitz::$plugin->settings->queryStringCaching = SettingsModel::QUERY_STRINGS_CACHE_URLS_AS_SAME_PAGE;
        $uri = Blitz::$plugin->cacheRequest->getRequestedCacheableSiteUri()->uri;
        $this->assertEquals($this->siteUri->uri, $uri);
    }

    public function testGetRequestedCacheableSiteUriWithRegularExpression()
    {
        $allowedQueryString = 'sort=asc&search=waldo';
        $disallowedQueryString = 'spidy=123';

        Blitz::$plugin->settings->excludedQueryStringParams = ['^(?!sort$|search$).*'];

        // Mock a URL request
        $this->_mockRequest($this->siteUri->getUrl().'?'.$disallowedQueryString.'&'.$allowedQueryString);

        // Enable caching and add an included URI pattern
        Blitz::$plugin->settings->cachingEnabled = true;
        Blitz::$plugin->settings->includedUriPatterns = [$this->uriPattern];

        Blitz::$plugin->settings->queryStringCaching = SettingsModel::QUERY_STRINGS_CACHE_URLS_AS_UNIQUE_PAGES;
        $uri = Blitz::$plugin->cacheRequest->getRequestedCacheableSiteUri()->uri;
        $this->assertEquals($this->siteUri->uri.'?'.$allowedQueryString, $uri);
    }

    public function testGetIsCacheableSiteUri()
    {
        // Assert that the site URI is not cacheable
        $this->assertFalse(Blitz::$plugin->cacheRequest->getIsCacheableSiteUri($this->siteUri));

        // Include the URI pattern
        Blitz::$plugin->settings->includedUriPatterns = [$this->uriPattern];
        $this->assertTrue(Blitz::$plugin->cacheRequest->getIsCacheableSiteUri($this->siteUri));

        // Exclude the excluded URI pattern works
        Blitz::$plugin->settings->excludedUriPatterns = [$this->uriPattern];
        $this->assertFalse(Blitz::$plugin->cacheRequest->getIsCacheableSiteUri($this->siteUri));
    }

    public function testMatchesUriPatterns()
    {
        // Ensure that catch-all pattern works
        $this->assertTrue(
            Blitz::$plugin->cacheRequest->matchesUriPatterns(
                $this->siteUri,
                [['siteId' => 1, 'uriPattern' => '.*']]
            )
        );

        // Ensure that pattern with escaped delimiter works
        $this->assertTrue(
            Blitz::$plugin->cacheRequest->matchesUriPatterns(
                $this->siteUri,
                [['siteId' => 1, 'uriPattern' => '(\/?)']]
            )
        );

        // Ensure that pattern with start and end characters works
        $this->assertTrue(
            Blitz::$plugin->cacheRequest->matchesUriPatterns(
                $this->siteUri,
                [['siteId' => 1, 'uriPattern' => '^'.$this->siteUri->uri.'$']]
            )
        );
        $this->assertFalse(
            Blitz::$plugin->cacheRequest->matchesUriPatterns(
                $this->siteUri,
                [['siteId' => 1, 'uriPattern' => '^'.substr($this->siteUri->uri, 0, -1).'$']]
            )
        );
    }

    public function testGetAllowedQueryString()
    {
        // Mock a URL request
        $this->_mockRequest($this->siteUri->getUrl().'?gclid=1&fbclid=2&page=3');

        $this->assertEquals('page=3', Blitz::$plugin->cacheRequest->getAllowedQueryString());
    }

    public function testGetResponse()
    {
        Blitz::$plugin->cacheStorage->deleteAll();

        // Assert that the response is null
        $this->assertNull(Blitz::$plugin->cacheRequest->getResponse($this->siteUri));

        // Save a value for the site URI
        Blitz::$plugin->cacheStorage->save('xyz', $this->siteUri);
        $value = Blitz::$plugin->cacheRequest->getResponse($this->siteUri)->data;

        // Assert that the response is not null
        $this->assertStringContainsString('xyz', $value);
    }

    public function testGetResponseWithOutputComments()
    {
        // Save a value for the site URI
        Blitz::$plugin->cacheStorage->save('xyz', $this->siteUri);

        Blitz::$plugin->settings->outputComments = false;
        $value = Blitz::$plugin->cacheRequest->getResponse($this->siteUri)->data;
        $this->assertStringNotContainsString('Served by Blitz on', $value);

        Blitz::$plugin->settings->outputComments = true;
        $value = Blitz::$plugin->cacheRequest->getResponse($this->siteUri)->data;
        $this->assertStringContainsString('Served by Blitz on', $value);

        Blitz::$plugin->settings->outputComments = SettingsModel::OUTPUT_COMMENTS_CACHED;
        $value = Blitz::$plugin->cacheRequest->getResponse($this->siteUri)->data;
        $this->assertStringNotContainsString('Served by Blitz on', $value);

        Blitz::$plugin->settings->outputComments = SettingsModel::OUTPUT_COMMENTS_SERVED;
        $value = Blitz::$plugin->cacheRequest->getResponse($this->siteUri)->data;
        $this->assertStringContainsString('Served by Blitz on', $value);
    }

    // Private methods
    // =========================================================================

    private function _mockRequest(string $url)
    {
        /**
         * Mock the web server request
         *
         * @see \putyourlightson\blitz\drivers\warmers\LocalWarmer::_warmUri
         */
        $uri = trim(parse_url($url, PHP_URL_PATH), '/');

        $_SERVER = array_merge($_SERVER, [
            'HTTP_HOST' => parse_url($url, PHP_URL_HOST),
            'SERVER_NAME' => parse_url($url, PHP_URL_HOST),
            'HTTPS' => parse_url($url, PHP_URL_SCHEME) === 'https',
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/'.$uri.'?'.parse_url($url, PHP_URL_QUERY),
            'QUERY_STRING' => parse_url($url, PHP_URL_QUERY),
        ]);
        $_POST = [];
        $_REQUEST = [];

        $request = Craft::createObject(App::webRequestConfig());

        Craft::$app->set('request', $request);
    }
}
