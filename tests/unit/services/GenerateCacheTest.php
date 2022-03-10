<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitztests\unit\services;

use Codeception\Test\Unit;
use Craft;
use craft\commerce\elements\Product;
use craft\db\FixedOrderExpression;
use craft\elements\Entry;
use craft\elements\User;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\models\SettingsModel;
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

    public function testSaveCache()
    {
        // Save the output for the site URI
        Blitz::$plugin->generateCache->save($this->output, $this->siteUri);
        $value = Blitz::$plugin->cacheStorage->get($this->siteUri);

        // Assert that the output contains the cached value
        $this->assertStringContainsString($this->output, $value);

        // Assert that the output contains a timestamp
        $this->assertStringContainsString('Cached by Blitz on', $value);
    }

    public function testSaveCacheWithOutputComments()
    {
        Blitz::$plugin->generateCache->options->outputComments = false;
        Blitz::$plugin->generateCache->save($this->output, $this->siteUri);
        $value = Blitz::$plugin->cacheStorage->get($this->siteUri);
        $this->assertStringNotContainsString('Cached by Blitz on', $value);

        Blitz::$plugin->generateCache->options->outputComments = true;
        Blitz::$plugin->generateCache->save($this->output, $this->siteUri);
        $value = Blitz::$plugin->cacheStorage->get($this->siteUri);
        $this->assertStringContainsString('Cached by Blitz on', $value);

        Blitz::$plugin->generateCache->options->outputComments = SettingsModel::OUTPUT_COMMENTS_CACHED;
        Blitz::$plugin->generateCache->save($this->output, $this->siteUri);
        $value = Blitz::$plugin->cacheStorage->get($this->siteUri);
        $this->assertStringContainsString('Cached by Blitz on', $value);

        Blitz::$plugin->generateCache->options->outputComments = SettingsModel::OUTPUT_COMMENTS_SERVED;
        Blitz::$plugin->generateCache->save($this->output, $this->siteUri);
        $value = Blitz::$plugin->cacheStorage->get($this->siteUri);
        $this->assertStringNotContainsString('Cached by Blitz on', $value);
    }

    public function testSaveCacheWithFileExtension()
    {
        $siteUri = new SiteUriModel(['siteId' => $this->siteUri->siteId]);

        $siteUri->uri = $this->siteUri->uri . '.html';

        Blitz::$plugin->generateCache->save($this->output, $siteUri);
        $value = Blitz::$plugin->cacheStorage->get($siteUri);

        // Assert that the output contains a timestamp
        $this->assertStringContainsString('Cached by Blitz on', $value);

        $siteUri->uri = $this->siteUri->uri . '.json';

        Blitz::$plugin->generateCache->save($this->output, $siteUri);
        $value = Blitz::$plugin->cacheStorage->get($siteUri);

        // Assert that the output does not contain a timestamp
        $this->assertStringNotContainsString('Cached by Blitz on', $value);
    }

    public function testSaveCacheRecord()
    {
        Blitz::$plugin->generateCache->save($this->output, $this->siteUri);
        $count = CacheRecord::find()
            ->where($this->siteUri->toArray())
            ->count();

        // Assert that the record was saved
        $this->assertEquals(1, $count);
    }

    public function testSaveElementCacheRecord()
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

    public function testSaveElementQueryRecords()
    {
        $elementQueries = [
            Entry::find()->id(1),
            Entry::find()->id('1'),
            Entry::find()->id('1, 2, 3'),
            Entry::find()->id([1, 2, 3]),
            Entry::find()->id(['1', '2', '3']),
            Entry::find()->slug('slug'),
            Entry::find()->orderBy('RAND()'),
            Entry::find()->orderBy('Rand(123)'),
        ];

        array_walk_recursive($elementQueries, [Blitz::$plugin->generateCache, 'addElementQuery']);
        $count = ElementQueryRecord::find()->count();

        // Assert that no records were saved
        $this->assertEquals(0, $count);

        $elementQueries = [
            [
                Entry::find(),
                Entry::find()->limit(''),
                Entry::find()->offset(0),
            ],
            Entry::find()->id('not 1'),
            [
                Entry::find()->id('not 1'),
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

    public function testSaveElementQueryWithJoin()
    {
        $elementQuery = Entry::find()->innerJoin('{{%users}}');
        Blitz::$plugin->generateCache->addElementQuery($elementQuery);

        $this->assertEquals(1, ElementQueryRecord::find()->count());
    }

    public function testSaveElementQueryWithRelations()
    {
        $elementQuery = Entry::find()->innerJoin('{{%relations}} relations');
        Blitz::$plugin->generateCache->addElementQuery($elementQuery);

        $this->assertEquals(0, ElementQueryRecord::find()->count());
    }

    public function testSaveElementQueryWithExpression()
    {
        $expression = new FixedOrderExpression('elements.id', [], Craft::$app->db);
        $elementQuery = Entry::find()->orderBy($expression);
        Blitz::$plugin->generateCache->addElementQuery($elementQuery);

        $this->assertEquals(0, ElementQueryRecord::find()->count());
    }

    public function testSaveElementQueryCacheRecords()
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

    public function testSaveElementQuerySourceRecords()
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

    public function testSaveCacheTags()
    {
        $tags = ['tag1', 'tag2', 'tag3'];
        Blitz::$plugin->generateCache->options->tags = $tags;

        Blitz::$plugin->generateCache->save($this->output, $this->siteUri);

        $cacheIds = Blitz::$plugin->cacheTags->getCacheIds(['xyz']);

        // Assert that zero cache IDs were found
        $this->assertCount(0, $cacheIds);

        $cacheIds = Blitz::$plugin->cacheTags->getCacheIds($tags);

        // Assert that one cache ID was found
        $this->assertCount(1, $cacheIds);
    }
}
