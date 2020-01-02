<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitztests\unit;

use Codeception\Test\Unit;
use craft\commerce\elements\Product;
use craft\elements\Entry;
use craft\elements\User;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\models\SiteUriModel;
use putyourlightson\blitz\records\CacheRecord;
use putyourlightson\blitz\records\ElementCacheRecord;
use putyourlightson\blitz\records\ElementQueryCacheRecord;
use putyourlightson\blitz\records\ElementQueryRecord;
use putyourlightson\blitz\records\ElementQuerySourceRecord;
use putyourlightson\campaign\elements\CampaignElement;
use putyourlightson\campaign\elements\MailingListElement;
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
    private $output = 'xyz';

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
    }

    // Public methods
    // =========================================================================

    public function testCacheSaved()
    {
        // Save the output for the site URI
        Blitz::$plugin->generateCache->save($this->output, $this->siteUri);
        $value = Blitz::$plugin->cacheStorage->get($this->siteUri);

        // Assert that the output (which may also contain a timestamp) contains the cached value
        $this->assertStringContainsString($this->output, $value);
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

    public function testElementQueryRecordsSaved()
    {
        $elementQueries = [
            [
                Entry::find(),
                Entry::find()->limit(''),
                Entry::find()->offset(0),
            ],
            Entry::find()->id('not 1'),
            [
                Entry::find()->id(['not', 1]),
                Entry::find()->id(['not', '1']),
            ],
            [
                Entry::find()->sectionId(1),
                Entry::find()->sectionId('1'),
                Entry::find()->sectionId([1]),
                Entry::find()->sectionId(['1']),
            ],
            [
                Entry::find()->sectionId('1, 2'),
                Entry::find()->sectionId([1, 2]),
            ],
        ];

        array_walk_recursive($elementQueries, [Blitz::$plugin->generateCache, 'addElementQuery']);
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

        array_walk_recursive($elementQueries, [Blitz::$plugin->generateCache, 'addElementQuery']);
        $count = ElementQueryRecord::find()->count();

        // Assert that no records were saved
        $this->assertEquals(0, $count);
    }

    public function testElementQueryCacheRecordsSaved()
    {
        $elementQuery = Entry::find();
        Blitz::$plugin->generateCache->addElementQuery($elementQuery);
        Blitz::$plugin->generateCache->save($this->output, $this->siteUri);

        $newSiteUri = new SiteUriModel(['siteId' => 1, 'uri' => 'new']);
        Blitz::$plugin->generateCache->save($this->output, $newSiteUri);

        $count = ElementQueryCacheRecord::find()->count();

        // Assert that two records were saved
        $this->assertEquals(2, $count);
    }

    public function testElementQuerySourceRecordsSaved()
    {
        $elementQueries = [
            Entry::find(),
            Entry::find()->sectionId(1),
            Entry::find()->sectionId([1, 2, 3]),
            Product::find()->typeId(4),
            CampaignElement::find()->campaignTypeId(5),
            MailingListElement::find()->mailingListTypeId(6),
        ];

        array_walk_recursive($elementQueries, [Blitz::$plugin->generateCache, 'addElementQuery']);
        $sourceIds = ElementQuerySourceRecord::find()->select('sourceId')->column();

        // Assert that source IDs were saved
        $this->assertEquals([null, 1, 1, 2, 3, 4, 5, 6], $sourceIds);
    }

    public function testElementQuerySourceRecordsNotSaved()
    {
        $elementQueries = [
            Entry::find()->sectionId('not 1'),
            Entry::find()->sectionId('> 1'),
            Entry::find()->sectionId(['not', 1]),
            Entry::find()->sectionId(['not', '1']),
            Entry::find()->sectionId(['>', '1']),
        ];

        array_walk_recursive($elementQueries, [Blitz::$plugin->generateCache, 'addElementQuery']);
        $count = ElementQuerySourceRecord::find()->count();

        // Assert that no records were saved
        $this->assertEquals(0, $count);
    }
}
