<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitztests\unit\services;

use Codeception\Test\Unit;
use Craft;
use craft\helpers\App;
use craft\helpers\UrlHelper;
use craft\web\Request;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\drivers\storage\FileStorage;
use putyourlightson\blitz\models\SettingsModel;
use putyourlightson\blitz\models\SiteUriModel;
use UnitTester;
use yii\web\Response;

/**
 * @since 3.0.0
 */

class CacheRequestTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected UnitTester $tester;

    /**
     * @var SiteUriModel
     */
    private SiteUriModel $siteUri;

    /**
     * @var array
     */
    private array $uriPattern;

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

    public function testGetIsInclude()
    {
        // Mock a URL request
        $this->_mockRequest($this->siteUri->getUrl());

        // Assert that the request is not a cached include request
        $this->assertFalse(Blitz::$plugin->cacheRequest->getIsCachedInclude());

        $this->_mockRequest(UrlHelper::siteUrl('_includes', ['action' => 'blitz/include/cached']));

        // Assert that the request is a cached include request
        $this->assertTrue(Blitz::$plugin->cacheRequest->getIsCachedInclude());
    }

    public function testGetIsIncludeWithUri()
    {
        $this->assertTrue(Blitz::$plugin->cacheRequest->getIsCachedInclude('/_includes?action='));
    }

    public function testGetRequestedCacheableSiteUri()
    {
        $pathParam = 'p=page';
        $allowedQueryString = 'x=1&y=1';
        $disallowedQueryString = 'gclid=123';

        // Mock a URL request
        $this->_mockRequest($this->siteUri->getUrl() . '?' .
            implode('&', [$pathParam, $disallowedQueryString, $allowedQueryString])
        );

        // Enable caching and add an included URI pattern
        Blitz::$plugin->settings->cachingEnabled = true;
        Blitz::$plugin->settings->includedUriPatterns = [$this->uriPattern];

        Blitz::$plugin->settings->queryStringCaching = SettingsModel::QUERY_STRINGS_CACHE_URLS_AS_UNIQUE_PAGES;
        $uri = Blitz::$plugin->cacheRequest->getRequestedCacheableSiteUri()->uri;
        $this->assertEquals($this->siteUri->uri . '?' . $allowedQueryString, $uri);

        Blitz::$plugin->settings->queryStringCaching = SettingsModel::QUERY_STRINGS_CACHE_URLS_AS_SAME_PAGE;
        $uri = Blitz::$plugin->cacheRequest->getRequestedCacheableSiteUri()->uri;
        $this->assertEquals($this->siteUri->uri, $uri);
    }

    public function testGetRequestedCacheableSiteUriWithPageTrigger()
    {
        Craft::$app->config->general->pageTrigger = 'p';

        // Mock a URL request
        $this->_mockRequest($this->siteUri->getUrl() . '/' . Craft::$app->config->general->pageTrigger . '1');

        // Enable caching and add an included URI pattern
        Blitz::$plugin->settings->cachingEnabled = true;
        Blitz::$plugin->settings->includedUriPatterns = [$this->uriPattern];

        $uri = Blitz::$plugin->cacheRequest->getRequestedCacheableSiteUri()->uri;
        $this->assertEquals($this->siteUri->uri . '/' . Craft::$app->config->general->pageTrigger . '1', $uri);
    }

    public function testGetRequestedCacheableSiteUriWithRegularExpression()
    {
        $allowedQueryString = 'sort=asc&search=waldo';
        $disallowedQueryString = 'spidy=123';

        Blitz::$plugin->settings->excludedQueryStringParams = [
            [
                'siteId' => '',
                'queryStringParam' => '^(?!sort$|search$).*',
            ],
        ];

        // Mock a URL request
        $this->_mockRequest($this->siteUri->getUrl() . '?' . $disallowedQueryString . '&' . $allowedQueryString);

        // Enable caching and add an included URI pattern
        Blitz::$plugin->settings->cachingEnabled = true;
        Blitz::$plugin->settings->includedUriPatterns = [$this->uriPattern];

        Blitz::$plugin->settings->queryStringCaching = SettingsModel::QUERY_STRINGS_CACHE_URLS_AS_UNIQUE_PAGES;
        $uri = Blitz::$plugin->cacheRequest->getRequestedCacheableSiteUri()->uri;
        $this->assertEquals($this->siteUri->uri . '?' . $allowedQueryString, $uri);
    }

    public function testGetIsCacheableSiteUri()
    {
        // Assert that the site URI is not cacheable
        $this->assertFalse(Blitz::$plugin->cacheRequest->getIsCacheableSiteUri($this->siteUri));

        // Include the URI pattern
        Blitz::$plugin->settings->includedUriPatterns = [$this->uriPattern];
        $this->assertTrue(Blitz::$plugin->cacheRequest->getIsCacheableSiteUri($this->siteUri));

        // Exclude the excluded URI pattern
        Blitz::$plugin->settings->excludedUriPatterns = [$this->uriPattern];
        $this->assertFalse(Blitz::$plugin->cacheRequest->getIsCacheableSiteUri($this->siteUri));
    }

    public function testGetIsCacheableSiteUriWithIndex()
    {
        Blitz::$plugin->settings->includedUriPatterns = [[
            'siteId' => '',
            'uriPattern' => '.*',
        ]];
        $siteUri = new SiteUriModel([
            'siteId' => 1,
            'uri' => 'index.php',
        ]);

        $this->assertFalse(Blitz::$plugin->cacheRequest->getIsCacheableSiteUri($siteUri));
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
                [['siteId' => 1, 'uriPattern' => '^' . $this->siteUri->uri . '$']]
            )
        );
        $this->assertFalse(
            Blitz::$plugin->cacheRequest->matchesUriPatterns(
                $this->siteUri,
                [['siteId' => 1, 'uriPattern' => '^' . substr($this->siteUri->uri, 0, -1) . '$']]
            )
        );
    }

    public function testGetAllowedQueryString()
    {
        $uri = $this->siteUri->uri . '?gclid=1&fbclid=2&page=3';

        $this->assertEquals('page=3', Blitz::$plugin->cacheRequest->getAllowedQueryString($this->siteUri->siteId, $uri));
    }

    public function testGetResponse()
    {
        Blitz::$plugin->cacheStorage->deleteAll();

        // Assert that the response is null
        $this->assertNull(Blitz::$plugin->cacheRequest->getCachedResponse($this->siteUri));

        // Save a value for the site URI
        Blitz::$plugin->cacheStorage->save('xyz', $this->siteUri);
        $value = Blitz::$plugin->cacheRequest->getCachedResponse($this->siteUri)->content;

        // Assert that the response is not null
        $this->assertStringContainsString('xyz', $value);
    }

    public function testGetResponseEncoded()
    {
        /** @var FileStorage $cacheStorage */
        $cacheStorage = Blitz::$plugin->cacheStorage;
        $cacheStorage->deleteAll();
        $cacheStorage->createGzipFiles = true;

        // Save a value for the site URI
        $output = 'xyz';
        $cacheStorage->save($output, $this->siteUri);

        Craft::$app->getRequest()->getHeaders()->set('Accept-Encoding', 'br, deflate, gzip');
        $response = Blitz::$plugin->cacheRequest->getCachedResponse($this->siteUri);

        // Assert that the response is encoded
        $this->assertEquals('gzip', $response->getHeaders()->get('Content-Encoding'));
        $this->assertStringContainsString($output, gzdecode($response->content));
    }

    public function testGetResponseWithOutputComments()
    {
        // Save a value for the site URI
        Blitz::$plugin->cacheStorage->save('xyz', $this->siteUri);

        Blitz::$plugin->settings->outputComments = false;
        Blitz::$plugin->generateCache->reset();
        $value = Blitz::$plugin->cacheRequest->getCachedResponse($this->siteUri)->content;
        $this->assertStringNotContainsString('Served by Blitz on', $value);

        Blitz::$plugin->settings->outputComments = true;
        Blitz::$plugin->generateCache->reset();
        $value = Blitz::$plugin->cacheRequest->getCachedResponse($this->siteUri)->content;
        $this->assertStringContainsString('Served by Blitz on', $value);

        Blitz::$plugin->settings->outputComments = SettingsModel::OUTPUT_COMMENTS_CACHED;
        Blitz::$plugin->generateCache->reset();
        $value = Blitz::$plugin->cacheRequest->getCachedResponse($this->siteUri)->content;
        $this->assertStringNotContainsString('Served by Blitz on', $value);

        Blitz::$plugin->settings->outputComments = SettingsModel::OUTPUT_COMMENTS_SERVED;
        Blitz::$plugin->generateCache->reset();
        $value = Blitz::$plugin->cacheRequest->getCachedResponse($this->siteUri)->content;
        $this->assertStringContainsString('Served by Blitz on', $value);

        Blitz::$plugin->settings->outputComments = false;
        Blitz::$plugin->generateCache->reset();
        Blitz::$plugin->generateCache->options->outputComments(true);
        $value = Blitz::$plugin->cacheRequest->getCachedResponse($this->siteUri)->content;
        $this->assertStringContainsString('Served by Blitz on', $value);
    }

    public function testGetResponseWithMimeType()
    {
        // Save a value for the site URI
        $this->siteUri->uri .= '.json';
        Blitz::$plugin->cacheStorage->save('xyz', $this->siteUri);

        Blitz::$plugin->settings->outputComments = true;
        $response = Blitz::$plugin->cacheRequest->getCachedResponse($this->siteUri);

        $this->assertEquals(Response::FORMAT_RAW, $response->format);
        $this->assertStringNotContainsString('Served by Blitz on', $response->content);
    }

    private function _mockRequest(string $url)
    {
        /**
         * Mock the web server request
         *
         * @see \craft\test\Craft::recreateClient()
         */
        $uri = trim(parse_url($url, PHP_URL_PATH), '/');
        $queryString = parse_url($url, PHP_URL_QUERY);

        $_SERVER = array_merge($_SERVER, [
            'HTTP_HOST' => parse_url($url, PHP_URL_HOST),
            'SERVER_NAME' => parse_url($url, PHP_URL_HOST),
            'HTTPS' => parse_url($url, PHP_URL_SCHEME) === 'https',
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/' . $uri . '?' . $queryString,
            'QUERY_STRING' => $queryString,
            'SCRIPT_NAME' => '/index.php',
        ]);
        parse_str($queryString, $queryStringParams);
        $_GET = $queryStringParams;
        $_POST = [];
        $_REQUEST = [];

        /** @var Request $request */
        $request = Craft::createObject(App::webRequestConfig());

        Craft::$app->set('request', $request);
    }
}
