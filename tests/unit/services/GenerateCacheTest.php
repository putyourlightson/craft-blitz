<?php
/**
 * @link      https://craftcampaign.com
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitztests\unit;

use Codeception\Test\Unit;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\models\SiteUriModel;
use UnitTester;

/**
 * @author    PutYourLightsOn
 * @package   Blitz
 * @since     2.3.0
 */

class GenerateCacheTest extends Unit
{
    // Properties
    // =========================================================================

    /**
     * @var UnitTester
     */
    protected $tester;

    // Protected methods
    // =========================================================================

    protected function _before()
    {
        parent::_before();

        Blitz::$plugin->generateCache->options->cachingEnabled = true;

        Blitz::$plugin->cacheStorage->deleteAll();
    }

    protected function _after()
    {
        Blitz::$plugin->cacheStorage->deleteAll();
    }

    // Public methods
    // =========================================================================

    public function testPageCached()
    {
        $siteUri = new SiteUriModel([
            'siteId' => 1,
            'uri' => 'page',
        ]);

        $output = 'xyz';

        // Assert that the statically cached file is empty
        $this->assertEmpty(Blitz::$plugin->cacheStorage->get($siteUri));

        Blitz::$plugin->generateCache->save($output, $siteUri);

        // Assert that the statically cached file contains the output
        $this->assertContains($output, Blitz::$plugin->cacheStorage->get($siteUri));
    }
}
