<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitztests\unit\services;

use Codeception\Test\Unit;
use Craft;
use craft\base\Element;
use craft\elements\Entry;
use craft\helpers\Db;
use DateInterval;
use DateTime;
use putyourlightson\blitz\behaviors\ElementChangedBehavior;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\helpers\FieldHelper;
use putyourlightson\blitz\helpers\RefreshCacheHelper;
use putyourlightson\blitz\jobs\RefreshCacheJob;
use putyourlightson\blitz\models\RefreshDataModel;
use putyourlightson\blitz\models\SiteUriModel;
use putyourlightson\blitz\records\ElementExpiryDateRecord;
use putyourlightson\blitz\records\ElementQueryRecord;
use putyourlightson\blitz\records\ElementQuerySourceRecord;
use putyourlightson\blitztests\fixtures\EntryFixture;
use UnitTester;

/**
 * @since 3.1.0
 */
class RefreshCacheTest extends Unit
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
     * @var string
     */
    private string $output = 'xyz';

    /**
     * @var Entry|ElementChangedBehavior|null
     */
    private Entry|ElementChangedBehavior|null $entry1 = null;

    /**
     * @var Entry|ElementChangedBehavior|null
     */
    private Entry|ElementChangedBehavior|null $entry2 = null;

    /**
     * @return array
     */
    public function _fixtures(): array
    {
        return [
            'entries' => [
                'class' => EntryFixture::class,
            ],
        ];
    }

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

        $this->entry1 = Entry::find()->sectionId(1)->one();
        $this->entry2 = Entry::find()->sectionId(2)->one();

        $this->entry1->attachBehavior(ElementChangedBehavior::BEHAVIOR_NAME, ElementChangedBehavior::class);
        $this->entry2->attachBehavior(ElementChangedBehavior::BEHAVIOR_NAME, ElementChangedBehavior::class);

        Blitz::$plugin->refreshCache->reset();
        Blitz::$plugin->refreshCache->batchMode = true;
    }

    public function testGetElementCacheIds()
    {
        Blitz::$plugin->generateCache->addElement($this->entry1);
        Blitz::$plugin->generateCache->save($this->output, $this->siteUri);

        $refreshData = RefreshDataModel::createFromElement($this->entry1);
        $cacheIds = RefreshCacheHelper::getElementCacheIds(
            Entry::class, $refreshData,
        );

        // Assert that one cache ID was returned
        $this->assertCount(1, $cacheIds);
    }

    public function testGetElementCacheIdsWithCustomFields()
    {
        Blitz::$plugin->generateCache->options->trackCustomFields(['text']);
        Blitz::$plugin->generateCache->addElement($this->entry1);
        Blitz::$plugin->generateCache->save($this->output, $this->siteUri);

        $fieldIds = FieldHelper::getFieldIdsFromHandles(['text']);
        $refreshData = RefreshDataModel::createFromElement($this->entry1);
        $refreshData->addChangedByFields($this->entry1, $fieldIds);
        $cacheIds = RefreshCacheHelper::getElementCacheIds(
            Entry::class, $refreshData,
        );

        $this->assertCount(1, $cacheIds);
    }

    public function testGetElementCacheIdsWithoutCustomFields()
    {
        Blitz::$plugin->generateCache->options->trackCustomFields(false);
        Blitz::$plugin->generateCache->addElement($this->entry1);
        Blitz::$plugin->generateCache->save($this->output, $this->siteUri);

        $fieldIds = FieldHelper::getFieldIdsFromHandles(['text']);
        $refreshData = RefreshDataModel::createFromElement($this->entry1);
        $refreshData->addChangedByFields($this->entry1, $fieldIds);
        $cacheIds = RefreshCacheHelper::getElementCacheIds(
            Entry::class, $refreshData,
        );

        $this->assertCount(0, $cacheIds);

        $refreshData = RefreshDataModel::createFromElement($this->entry1);
        $refreshData->addChangedByFields($this->entry1, []);
        $cacheIds = RefreshCacheHelper::getElementCacheIds(
            Entry::class, $refreshData,
        );

        $this->assertCount(1, $cacheIds);
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
        $db->createCommand()->insert(
            ElementQueryRecord::tableName(),
            [
                'index' => 1234567890,
                'type' => Entry::class,
                'params' => '[]',
            ]
        )
        ->execute();
        $queryId = $db->getLastInsertID();

        // Add source ID
        $db->createCommand()->insert(
            ElementQuerySourceRecord::tableName(),
            [
                'queryId' => $queryId,
                'sourceId' => $this->entry1->sectionId,
            ]
        )
        ->execute();

        $refreshData = RefreshDataModel::createFromElement($this->entry1);
        $elementTypeQueries = RefreshCacheHelper::getElementTypeQueryRecords(
            Entry::class, $refreshData,
        );

        $this->assertCount(2, $elementTypeQueries);

        $refreshData = RefreshDataModel::createFromElement($this->entry2);
        $elementTypeQueries = RefreshCacheHelper::getElementTypeQueryRecords(
            Entry::class, $refreshData,
        );

        $this->assertCount(1, $elementTypeQueries);
    }

    public function testAddElementWhenUnchanged()
    {
        Blitz::$plugin->refreshCache->addElement($this->entry1);

        // Assert that the refresh data is empty
        $this->assertEmpty(Blitz::$plugin->refreshCache->refreshData->data['elements']);
    }

    public function testAddElementWhenAttributeChanged()
    {
        $this->entry1->title .= ' X';
        Blitz::$plugin->refreshCache->addElement($this->entry1);

        // Assert that the tracked element is correct
        $this->_assertTrackedElement($this->entry1);
    }

    public function testAddElementWhenFieldChanged()
    {
        $this->entry1->setFieldValue('text', '123');
        Blitz::$plugin->refreshCache->addElement($this->entry1);

        $fieldIds = FieldHelper::getFieldIdsFromHandles(['text', '123']);

        // Assert that the tracked element is correct
        $this->_assertTrackedElement($this->entry1, $fieldIds);
    }

    public function testAddElementMultipleTimesWhenAttributesChanged()
    {
        $this->entry1->title .= ' X';
        Blitz::$plugin->refreshCache->addElement($this->entry1);

        $this->entry1->setFieldValue('text', '123');
        Blitz::$plugin->refreshCache->addElement($this->entry1);

        // Assert that the tracked element is correct
        $this->_assertTrackedElement($this->entry1);
    }

    public function testAddElementMultipleTimesWhenFieldsChanged()
    {
        $this->entry1->setFieldValue('text', '123');
        Blitz::$plugin->refreshCache->addElement($this->entry1);

        $this->entry1->setFieldValue('moreText', '123');
        Blitz::$plugin->refreshCache->addElement($this->entry1);

        $fieldIds = FieldHelper::getFieldIdsFromHandles(['text', 'moreText']);

        // Assert that the tracked element is correct
        $this->_assertTrackedElement($this->entry1, $fieldIds);
    }

    public function testAddElementMultipleTimesWhenAllFieldsChanged()
    {
        $this->entry1->setFieldValue('text', '123');
        Blitz::$plugin->refreshCache->addElement($this->entry1);

        $this->entry1->setFieldValue('moreText', '123');
        Blitz::$plugin->refreshCache->addElement($this->entry1);

        $this->entry1->setFieldValue('evenMoreText', '123');
        Blitz::$plugin->refreshCache->addElement($this->entry1);

        // Assert that the tracked element is correct
        $this->_assertTrackedElement($this->entry1, true);
    }

    public function testAddElementWhenAttributeAndFieldChanged()
    {
        $this->entry1->title .= ' X';
        $this->entry1->setFieldValue('text', '123');
        Blitz::$plugin->refreshCache->addElement($this->entry1);

        // Assert that the tracked element is correct
        $this->_assertTrackedElement($this->entry1);
    }

    public function testAddElementWhenStatusChanged()
    {
        $this->entry1->title .= ' X';

        $this->entry1->originalElement->enabled = false;
        $this->entry1->enabled = false;
        Blitz::$plugin->refreshCache->addElement($this->entry1);

        // Assert that the refresh data is empty
        $this->assertEmpty(Blitz::$plugin->refreshCache->refreshData->data['elements']);

        $this->entry1->originalElement->enabled = true;
        Blitz::$plugin->refreshCache->addElement($this->entry1);

        // Assert that the tracked element is correct
        $this->_assertTrackedElement($this->entry1);
    }

    public function testAddElementWhenExpired()
    {
        // Set the expiryData in the past
        $this->entry1->expiryDate = new DateTime('20010101');
        Blitz::$plugin->refreshCache->addElement($this->entry1);

        // Assert that the tracked element is correct
        $this->_assertTrackedElement($this->entry1);
    }

    public function testAddElementWhenDeleted()
    {
        // Delete the element
        Craft::$app->getElements()->deleteElement($this->entry1);

        // Assert that the tracked element is correct
        $this->_assertTrackedElement($this->entry1);
    }

    public function testAddElementExpiryDates()
    {
        $this->entry1->expiryDate = (new DateTime('now'))->add(new DateInterval('P2D'));

        Blitz::$plugin->refreshCache->addElementExpiryDates($this->entry1);

        /** @var ElementExpiryDateRecord $elementExpiryDateRecord */
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

        /** @var ElementExpiryDateRecord $elementExpiryDateRecord */
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

        $refreshData = RefreshDataModel::createFromElement($this->entry1);
        $refreshCacheJob = new RefreshCacheJob([
            'data' => $refreshData->data,
            'forceClear' => true,
        ]);
        $refreshCacheJob->execute(Craft::$app->getQueue());

        // Assert that the cached value is a blank string
        $this->assertEquals('', Blitz::$plugin->cacheStorage->get($this->siteUri));
    }

    public function testRefreshSourceTag()
    {
        // Add source tag and save
        Blitz::$plugin->generateCache->options->tags('sectionId:' . $this->entry1->sectionId);
        Blitz::$plugin->generateCache->save($this->output, $this->siteUri);

        // Assert that the output (which may also contain a timestamp) contains the cached value
        $this->assertStringContainsString($this->output, Blitz::$plugin->cacheStorage->get($this->siteUri));

        $refreshData = new RefreshDataModel();
        $refreshData->addSourceId($this->entry1::class, $this->entry1->sectionId);
        $refreshCacheJob = new RefreshCacheJob([
            'data' => $refreshData->data,
            'forceClear' => true,
        ]);
        $refreshCacheJob->execute(Craft::$app->getQueue());

        // Assert that the cached value is a blank string
        $this->assertEquals('', Blitz::$plugin->cacheStorage->get($this->siteUri));
    }

    public function testRefreshCachedUrls()
    {
        Blitz::$plugin->generateCache->save($this->output, $this->siteUri);

        // Assert that the output (which may also contain a timestamp) contains the cached value
        $this->assertStringContainsString($this->output, Blitz::$plugin->cacheStorage->get($this->siteUri));

        Blitz::$plugin->refreshCache->refreshCachedUrls([$this->siteUri->url]);

        Craft::$app->runAction('queue/run');

        // Assert that the cached value is a blank string
        $this->assertEquals('', Blitz::$plugin->cacheStorage->get($this->siteUri));
    }

    public function testRefreshCachedUrlsWithWildcard()
    {
        Blitz::$plugin->generateCache->save($this->output, $this->siteUri);

        // Assert that the output (which may also contain a timestamp) contains the cached value
        $this->assertStringContainsString($this->output, Blitz::$plugin->cacheStorage->get($this->siteUri));

        $url = substr($this->siteUri->url, 0, -1) . '*';
        Blitz::$plugin->refreshCache->refreshCachedUrls([$url]);

        Craft::$app->runAction('queue/run');

        // Assert that the cached value is a blank string
        $this->assertEquals('', Blitz::$plugin->cacheStorage->get($this->siteUri));
    }

    public function testRefreshCacheTags()
    {
        // Add tag and save
        $tag = 'abc';
        Blitz::$plugin->generateCache->options->tags($tag);
        Blitz::$plugin->generateCache->save($this->output, $this->siteUri);

        // Assert that the output (which may also contain a timestamp) contains the cached value
        $this->assertStringContainsString($this->output, Blitz::$plugin->cacheStorage->get($this->siteUri));

        Blitz::$plugin->refreshCache->refreshCacheTags([$tag]);

        Craft::$app->runAction('queue/run');

        // Assert that the cached value is a blank string
        $this->assertEquals('', Blitz::$plugin->cacheStorage->get($this->siteUri));
    }

    private function _assertTrackedElement(Element|ElementChangedBehavior $element, array|bool $changedByFields = false)
    {
        $refreshData = Blitz::$plugin->refreshCache->refreshData;

        $this->assertEquals(
            $changedByFields,
            $refreshData->getChangedByFields($element::class, $element->id),
        );

        $this->assertEquals(
            !empty($element->sectionId) ? [$element->sectionId] : [],
            $refreshData->getSourceIds($element::class),
        );
    }
}
