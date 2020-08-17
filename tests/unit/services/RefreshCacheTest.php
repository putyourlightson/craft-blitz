<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitztests\unit;

use Codeception\Test\Unit;
use Craft;
use craft\base\Element;
use craft\elements\Entry;
use craft\helpers\Db;
use DateInterval;
use DateTime;
use putyourlightson\blitz\behaviors\ElementChangedBehavior;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\jobs\RefreshCacheJob;
use putyourlightson\blitz\models\SiteUriModel;
use putyourlightson\blitz\records\ElementExpiryDateRecord;
use putyourlightson\blitz\records\ElementQueryRecord;
use putyourlightson\blitz\records\ElementQuerySourceRecord;
use putyourlightson\blitztests\fixtures\EntriesFixture;
use putyourlightson\blitztests\fixtures\SitesGroupsFixture;
use UnitTester;

/**
 * @author    PutYourLightsOn
 * @package   Blitz
 * @since     3.1.0
 */

class RefreshCacheTest extends Unit
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

    /**
     * @var Entry
     */
    private $entry1;

    /**
     * @var Entry
     */
    private $entry2;

    // Fixtures
    // =========================================================================

    /**
     * @return array
     */
    public function _fixtures(): array
    {
        return [
            'entries' => [
                'class' => EntriesFixture::class
            ],
        ];
    }

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

        $this->entry1 = Entry::find()->slug('entry-1')->one();
        $this->entry2 = Entry::find()->slug('entry-2')->one();

        if ($this->entry1 === null) {
            $this->entry1 = new Entry([
                'authorId' => '1',
                'sectionId' => '1',
                'typeId' => '1',
                'title' => 'Entry 1',
                'slug' => 'entry-1',
            ]);
            Craft::$app->getElements()->saveElement($this->entry1);
        }

        if ($this->entry2 === null) {
            $this->entry2 = new Entry([
                'authorId' => '1',
                'sectionId' => '2',
                'typeId' => '2',
                'title' => 'Entry 2',
                'slug' => 'entry-2',
            ]);
            Craft::$app->getElements()->saveElement($this->entry2);
        }
    }

    // Public methods
    // =========================================================================

    public function testGetElementCacheIds()
    {
        Blitz::$plugin->generateCache->addElement($this->entry1);
        Blitz::$plugin->generateCache->save($this->output, $this->siteUri);

        $cacheIds = Blitz::$plugin->refreshCache->getElementCacheIds([$this->entry1->id]);

        // Assert that one cache ID was returned
        $this->assertEquals(1, count($cacheIds));
    }

    public function testGetElementTypeQueries()
    {
        // Add element queries and save
        $elementQuery1 = Entry::find();
        $elementQuery2 = Entry::find()->sectionId($this->entry1->sectionId);
        Blitz::$plugin->generateCache->addElementQuery($elementQuery1);
        Blitz::$plugin->generateCache->addElementQuery($elementQuery2);
        Blitz::$plugin->generateCache->save($this->output, $this->siteUri);

        // Add a rogue element query (without a cache ID)
        $db = Craft::$app->getDb();
        $db->createCommand()
            ->insert(ElementQueryRecord::tableName(), [
                'index' => 1234567890,
                'type' => Entry::class,
                'params' => '[]',
            ], false)
            ->execute();
        $queryId = $db->getLastInsertID();

        // Add source ID
        $db->createCommand()
            ->insert(ElementQuerySourceRecord::tableName(), [
                'sourceId' => $this->entry1->sectionId,
                'queryId' => $queryId,
            ], false)
            ->execute();

        $elementTypeQueries = Blitz::$plugin->refreshCache->getElementTypeQueries(
            Entry::class, [$this->entry1->sectionId], []
        );

        // Assert that two element type queries were returned
        $this->assertEquals(2, count($elementTypeQueries));

        $elementTypeQueries = Blitz::$plugin->refreshCache->getElementTypeQueries(
            Entry::class, [$this->entry2->sectionId], []
        );

        // Assert that one element type query was returned
        $this->assertEquals(1, count($elementTypeQueries));
    }

    public function testAddElement()
    {
        Blitz::$plugin->refreshCache->elements = [];
        Blitz::$plugin->refreshCache->batchMode = true;

        $this->entry1->attachBehavior(ElementChangedBehavior::BEHAVIOR_NAME, ElementChangedBehavior::class);
        Blitz::$plugin->refreshCache->addElement($this->entry1);

        // Assert that the element and source IDs are empty
        $this->assertEquals(
            [
                'elementIds' => [],
                'sourceIds' => [],
            ],
            Blitz::$plugin->refreshCache->elements[Entry::class]
        );

        // Update the title
        $this->entry1->title .= ' X';
        Blitz::$plugin->refreshCache->addElement($this->entry1);

        // Assert that the element and source IDs are correct
        $this->assertEquals(
            [
                'elementIds' => [$this->entry1->id],
                'sourceIds' => [$this->entry1->sectionId],
            ],
            Blitz::$plugin->refreshCache->elements[Entry::class]
        );

        $this->entry2->attachBehavior(ElementChangedBehavior::BEHAVIOR_NAME, ElementChangedBehavior::class);

        // Update the title
        $this->entry2->title .= ' X';

        // Change the statuses to disabled
        $this->entry2->previousStatus = Element::STATUS_DISABLED;
        $this->entry2->enabled = false;
        Blitz::$plugin->refreshCache->addElement($this->entry2);

        // Assert that the element and source IDs are the same as before
        $this->assertEquals(
            [
                'elementIds' => [$this->entry1->id],
                'sourceIds' => [$this->entry1->sectionId],
            ],
            Blitz::$plugin->refreshCache->elements[Entry::class]
        );

        // Change the previous status to live
        $this->entry2->previousStatus = Entry::STATUS_LIVE;
        Blitz::$plugin->refreshCache->addElement($this->entry2);

        // Assert that the element and source IDs are correct
        $this->assertEquals(
            [
                'elementIds' => [$this->entry1->id, $this->entry2->id],
                'sourceIds' => [$this->entry1->sectionId, $this->entry2->sectionId],
            ],
            Blitz::$plugin->refreshCache->elements[Entry::class]
        );

        // Delete the element
        Blitz::$plugin->refreshCache->elements = [];
        Craft::$app->getElements()->deleteElement($this->entry1);

        // Assert that the element and source IDs are correct
        $this->assertEquals(
            [
                'elementIds' => [$this->entry1->id],
                'sourceIds' => [$this->entry1->sectionId],
            ],
            Blitz::$plugin->refreshCache->elements[Entry::class]
        );
    }

    public function testAddElementExpiryDates()
    {
        $this->entry1->expiryDate = (new DateTime('now'))->add(new DateInterval('P2D'));

        Blitz::$plugin->refreshCache->addElementExpiryDates($this->entry1);

        $elementExpiryDateRecord = ElementExpiryDateRecord::find()
            ->where(['elementId' => $this->entry1->id])
            ->one();

        // Assert that the expiry date is correct
        $this->assertEquals(
            Db::prepareDateForDb($this->entry1->expiryDate),
            $elementExpiryDateRecord->expiryDate
        );

        $this->entry1->postDate = (new DateTime('now'))->add(new DateInterval('P1D'));

        Blitz::$plugin->refreshCache->addElementExpiryDates($this->entry1);

        $elementExpiryDateRecord = ElementExpiryDateRecord::find()
            ->where(['elementId' => $this->entry1->id])
            ->one();

        // Assert that the expiry date is correct
        $this->assertEquals(
            Db::prepareDateForDb($this->entry1->postDate),
            $elementExpiryDateRecord->expiryDate
        );
    }

    public function testRefreshElementQuery()
    {
        // Add element query and save
        $elementQuery = Entry::find()->sectionId($this->entry1->sectionId);
        Blitz::$plugin->generateCache->addElementQuery($elementQuery);
        Blitz::$plugin->generateCache->save($this->output, $this->siteUri);

        // Assert that the output (which may also contain a timestamp) contains the cached value
        $this->assertStringContainsString($this->output, Blitz::$plugin->cacheStorage->get($this->siteUri));

        $refreshCacheJob = new RefreshCacheJob([
            'cacheIds' => [],
            'elements' => [
                Entry::class => [
                    'elementIds' => [$this->entry1->id],
                    'sourceIds' => [$this->entry1->sectionId],
                ],
            ],
            'clearCache' => true,
        ]);
        $refreshCacheJob->execute(Craft::$app->getQueue());

        // Assert that the cache ID was found
        $this->assertEquals([1], $refreshCacheJob->cacheIds);

        // Assert that the cached value is a blank string
        $this->assertEquals('', Blitz::$plugin->cacheStorage->get($this->siteUri));
    }

    public function testRefreshSourceTag()
    {
        // Add source tag and save
        Blitz::$plugin->generateCache->options->tags('sectionId:'.$this->entry1->sectionId);
        Blitz::$plugin->generateCache->save($this->output, $this->siteUri);

        // Assert that the output (which may also contain a timestamp) contains the cached value
        $this->assertStringContainsString($this->output, Blitz::$plugin->cacheStorage->get($this->siteUri));

        $refreshCacheJob = new RefreshCacheJob([
            'cacheIds' => [],
            'elements' => [
                Entry::class => [
                    'elementIds' => [],
                    'sourceIds' => [$this->entry1->sectionId],
                ],
            ],
            'clearCache' => true,
        ]);
        $refreshCacheJob->execute(Craft::$app->getQueue());

        // Assert that the cache ID was found
        $this->assertEquals([1], $refreshCacheJob->cacheIds);

        // Assert that the cached value is a blank string
        $this->assertEquals('', Blitz::$plugin->cacheStorage->get($this->siteUri));
    }
}
