<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitztests\unit;

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
        Blitz::$plugin->settings->includedUriPatterns = [$this->uriPattern];

        // Hide the fact that this is a console request
        Craft::$app->getRequest()->isConsoleRequest = false;

        // Assert that the request is cacheable
        $this->assertTrue(Blitz::$plugin->cacheRequest->getIsCacheableRequest());
    }

    public function testGetIsCacheableRequestWithPathParam()
    {
        // Mock a URL request
        $this->_mockRequest($this->siteUri->getUrl().'?p=search');

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

    public function testGetIsCacheableSiteUri()
    {
        // Assert that the site URI is not cacheable
        $this->assertFalse(Blitz::$plugin->cacheRequest->getIsCacheableSiteUri($this->siteUri));

        // Include the URI pattern
        Blitz::$plugin->settings->includedUriPatterns = [$this->uriPattern];

        // Assert that the site URI is cacheable
        $this->assertTrue(Blitz::$plugin->cacheRequest->getIsCacheableSiteUri($this->siteUri));

        // Exclude the URI pattern
        Blitz::$plugin->settings->excludedUriPatterns = [$this->uriPattern];

        // Assert that the site URI is not cacheable
        $this->assertFalse(Blitz::$plugin->cacheRequest->getIsCacheableSiteUri($this->siteUri));
    }

    public function testGetResponse()
    {
        Blitz::$plugin->cacheStorage->deleteAll();

        // Assert that the response is null
        $this->assertNull(Blitz::$plugin->cacheRequest->getResponse($this->siteUri));

        // Save a value for the site URI
        Blitz::$plugin->cacheStorage->save('xyz', $this->siteUri);

        // Assert that the response is not null
        $this->assertNotNull(Blitz::$plugin->cacheRequest->getResponse($this->siteUri));
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
