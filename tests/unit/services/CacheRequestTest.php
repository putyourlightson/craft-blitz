<?php
/**
 * @link      https://craftcampaign.com
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitztests\unit;

use Codeception\Test\Unit;
use Craft;
use putyourlightson\blitz\Blitz;
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

    // Protected methods
    // =========================================================================

    protected function _before()
    {
        parent::_before();

        $this->siteUri = new SiteUriModel([
            'siteId' => 1,
            'uri' => 'page',
        ]);
    }

    // Public methods
    // =========================================================================

    public function testGetIsCacheableRequest()
    {
        // Assert that the request is not cacheable
        $this->assertFalse(Blitz::$plugin->cacheRequest->getIsCacheableRequest());

        Blitz::$plugin->settings->cachingEnabled = true;

        $request = Craft::$app->getRequest();
        $request->isConsoleRequest = false;

        // Assert that the request is cacheable
        $this->assertTrue(Blitz::$plugin->cacheRequest->getIsCacheableRequest());

        $_SERVER['QUERY_STRING'] = 'x=1';

        // Assert that the request is not cacheable
        $this->assertFalse(Blitz::$plugin->cacheRequest->getIsCacheableRequest());
    }

    public function testGetIsCacheableSiteUri()
    {
        $uriPattern = [
            'siteId' => $this->siteUri->siteId,
            'uriPattern' => $this->siteUri->uri,
        ];

        // Assert that the site URI is not cacheable
        $this->assertFalse(Blitz::$plugin->cacheRequest->getIsCacheableSiteUri($this->siteUri));

        Blitz::$plugin->settings->includedUriPatterns = [$uriPattern];

        // Assert that the site URI is cacheable
        $this->assertTrue(Blitz::$plugin->cacheRequest->getIsCacheableSiteUri($this->siteUri));

        Blitz::$plugin->settings->excludedUriPatterns = [$uriPattern];

        // Assert that the site URI is not cacheable
        $this->assertFalse(Blitz::$plugin->cacheRequest->getIsCacheableSiteUri($this->siteUri));
    }

    public function testGetResponse()
    {
        Blitz::$plugin->cacheStorage->deleteAll();

        // Assert that the response is null
        $this->assertNull(Blitz::$plugin->cacheRequest->getResponse($this->siteUri));

        Blitz::$plugin->cacheStorage->save('xyz', $this->siteUri);

        // Assert that the response is not null
        $this->assertNotNull(Blitz::$plugin->cacheRequest->getResponse($this->siteUri));
    }
}
