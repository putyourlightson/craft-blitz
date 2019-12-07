<?php
/**
 * @link      https://craftcampaign.com
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitztests\unit;

use Codeception\Test\Unit;
use craft\elements\Entry;
use craft\elements\User;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\models\SiteUriModel;
use putyourlightson\blitz\records\CacheRecord;
use putyourlightson\blitz\records\ElementCacheRecord;
use putyourlightson\blitz\records\ElementQueryCacheRecord;
use putyourlightson\blitz\records\ElementQueryRecord;
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

    /**
     * @var SiteUriModel
     */
    private $siteUri;

    /**
     * @var string
     */
    private $output;

    // Protected methods
    // =========================================================================

    protected function _before()
    {
        parent::_before();

        Blitz::$plugin->generateCache->options->cachingEnabled = true;

        Blitz::$plugin->cacheStorage->deleteAll();
        Blitz::$plugin->flushCache->flushAll();

        $this->siteUri = new SiteUriModel([
            'siteId' => 1,
            'uri' => 'page',
        ]);

        $this->output = 'xyz';
    }

    // Public methods
    // =========================================================================

    public function testCacheSaved()
    {
        Blitz::$plugin->generateCache->save($this->output, $this->siteUri);

        // Assert that the statically cached file contains the output
        $this->assertStringContainsString($this->output, Blitz::$plugin->cacheStorage->get($this->siteUri));
    }

    public function testCacheRecordSaved()
    {
        Blitz::$plugin->generateCache->save($this->output, $this->siteUri);

        $count = CacheRecord::find()
            ->where($this->siteUri->toArray())
            ->count();

        // Assert that the record was saved
        $this->assertEquals(1, $count);
    }

    public function testElementCacheRecordSaved()
    {
        $element = User::find()->one();
        Blitz::$plugin->generateCache->addElement($element);

        Blitz::$plugin->generateCache->save($this->output, $this->siteUri);

        $count = ElementCacheRecord::find()
            ->where(['elementId' => $element->id])
            ->count();

        // Assert that the record was saved
        $this->assertEquals(1, $count);
    }

    public function testElementQueryCacheRecordSaved()
    {
        $elementQuery = Entry::find();
        Blitz::$plugin->generateCache->addElementQuery($elementQuery);

        Blitz::$plugin->generateCache->save($this->output, $this->siteUri);

        $count = ElementQueryCacheRecord::find()->count();

        // Assert that the record was saved
        $this->assertEquals(1, $count);
    }

    public function testElementQueryRecordsSaved()
    {
        $elementQueries = [
            Entry::find(),
            Entry::find()->id('not 1'),
            Entry::find()->id(['not', 1]),
            Entry::find()->id(['not', '1']),
        ];

        foreach ($elementQueries as $elementQuery) {
            Blitz::$plugin->generateCache->addElementQuery($elementQuery);
        }

        $count = ElementQueryRecord::find()->count();

        // Assert that all records were saved
        $this->assertEquals(count($elementQueries), $count);
    }

    public function testElementQueryRecordsNotSaved()
    {
        $elementQueries = [
            Entry::find()->id(1),
            Entry::find()->id('1'),
            Entry::find()->id('1, 2, 3'),
            Entry::find()->id([1, 2, 3]),
            Entry::find()->id(['1', '2', '3']),
        ];

        foreach ($elementQueries as $elementQuery) {
            Blitz::$plugin->generateCache->addElementQuery($elementQuery);
        }

        $count = ElementQueryRecord::find()->count();

        // Assert that no records were saved
        $this->assertEquals(0, $count);
    }
}
