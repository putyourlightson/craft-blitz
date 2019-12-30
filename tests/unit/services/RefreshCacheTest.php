<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitztests\unit;

use Codeception\Test\Unit;
use Craft;
use craft\elements\Entry;
use craft\helpers\Db;
use crafttests\fixtures\EntryFixture;
use DateInterval;
use DateTime;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\helpers\ElementTypeHelper;
use putyourlightson\blitz\jobs\RefreshCacheJob;
use putyourlightson\blitz\models\SiteUriModel;
use putyourlightson\blitz\records\ElementExpiryDateRecord;
use putyourlightson\blitz\records\ElementQueryRecord;
use putyourlightson\blitz\records\ElementQuerySourceRecord;
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
                'class' => EntryFixture::class
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

        $this->entry1 = Entry::find()->sectionId(1000)->one();
        $this->entry2 = Entry::find()->sectionId(1003)->one();
    }

    // Public methods
    // =========================================================================

    public function testGetElementCacheIds()
    {
        Blitz::$plugin->generateCache->addElement($this->entry1);
        Blitz::$plugin->generateCache->save($this->output, $this->siteUri);

        $cacheIds = Blitz::$plugin->refreshCache->getElementCacheIds(
            [$this->entry1->id], []
        );

        // Assert that one cache ID was returned
        $this->assertEquals(1, count($cacheIds));

        $cacheIds = Blitz::$plugin->refreshCache->getElementCacheIds(
            [$this->entry1->id], $cacheIds
        );

        // Assert that one cache ID was returned
        $this->assertEquals(1, count($cacheIds));

        $cacheIds = Blitz::$plugin->refreshCache->getElementCacheIds(
            [$this->entry1->id], [999]
        );

        // Assert that two cache ID were returned
        $this->assertEquals(2, count($cacheIds));
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

        Blitz::$plugin->refreshCache->addElement($this->entry1);

        // Assert that the element and source IDs are correct
        $this->assertEquals(
            [
                'elementIds' => [$this->entry1->id],
                'sourceIds' => [$this->entry1->sectionId]
            ],
            Blitz::$plugin->refreshCache->elements[Entry::class]
        );

        Blitz::$plugin->refreshCache->addElement($this->entry2);

        // Assert that the element and source IDs are correct
        $this->assertEquals(
            [
                'elementIds' => [$this->entry1->id, $this->entry2->id],
                'sourceIds' => [$this->entry1->sectionId, $this->entry2->sectionId]
            ],
            Blitz::$plugin->refreshCache->elements[Entry::class]
        );
    }

    public function testAddElementExpiryDates()
    {
        $now = new DateTime('now');
        $this->entry1->postDate = $now->add(new DateInterval('P1D'));
        $this->entry1->expiryDate = $now->add(new DateInterval('P2D'));

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

    public function testRefreshCacheJob()
    {
        // Add element queries and save
        $elementQuery = Entry::find()->sectionId($this->entry1->sectionId);
        Blitz::$plugin->generateCache->addElementQuery($elementQuery);
        Blitz::$plugin->generateCache->save($this->output, $this->siteUri);

        // Assert that the output (which may also contain a timestamp) contains the cached value
        $this->assertStringContainsString($this->output, Blitz::$plugin->cacheStorage->get($this->siteUri));

        $refreshCacheJob = new RefreshCacheJob([
            'cacheIds' => [],
            'elements' => [
                Entry::class => [
                    'elementIds' => [$this->entry2->id],
                    'sourceIds' => [$this->entry2->sectionId],
                ],
            ],
            'clearCache' => true,
        ]);
        $refreshCacheJob->execute(Craft::$app->getQueue());

        // Assert that zero cache IDs were found
        $this->assertEquals(0, count($refreshCacheJob->cacheIds));

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
}
