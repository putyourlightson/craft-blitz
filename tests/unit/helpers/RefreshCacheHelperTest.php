<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitztests\unit\helpers;

use Codeception\Test\Unit;
use Craft;
use craft\elements\Entry;
use putyourlightson\blitz\behaviors\ElementChangedBehavior;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\helpers\RefreshCacheHelper;
use putyourlightson\blitz\models\RefreshDataModel;
use putyourlightson\blitz\models\SiteUriModel;
use putyourlightson\blitz\records\ElementQueryAttributeRecord;
use putyourlightson\blitz\records\ElementQueryRecord;
use putyourlightson\blitz\records\ElementQuerySourceRecord;
use putyourlightson\blitztests\fixtures\EntryFixture;
use UnitTester;

/**
 * @since 4.4.0
 */
class RefreshCacheHelperTest extends Unit
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
     * @var Entry|ElementChangedBehavior
     */
    private Entry|ElementChangedBehavior $entry1;

    /**
     * @var Entry|ElementChangedBehavior
     */
    private Entry|ElementChangedBehavior $entry2;

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

    public function testGetElementCacheIdsWithAttributes()
    {
        Blitz::$plugin->generateCache->addElement($this->entry1);
        Blitz::$plugin->generateCache->save($this->output, $this->siteUri);

        $refreshData = RefreshDataModel::createFromElement($this->entry1);
        $refreshData->addChangedField($this->entry1, 'text');
        $refreshData->addChangedAttribute($this->entry1, 'title');
        $cacheIds = RefreshCacheHelper::getElementCacheIds(
            Entry::class, $refreshData,
        );

        $this->assertCount(1, $cacheIds);
    }

    public function testGetElementCacheIdsWithCustomFields()
    {
        Blitz::$plugin->generateCache->addElement($this->entry1);
        /** @noinspection PhpUnusedLocalVariableInspection */
        $text = $this->entry1->text;
        Blitz::$plugin->generateCache->save($this->output, $this->siteUri);

        $refreshData = RefreshDataModel::createFromElement($this->entry1);
        $refreshData->addChangedField($this->entry1, 'text');
        $cacheIds = RefreshCacheHelper::getElementCacheIds(
            Entry::class, $refreshData,
        );

        $this->assertCount(1, $cacheIds);
    }

    public function testGetElementCacheIdsWithoutCustomFields()
    {
        Blitz::$plugin->generateCache->addElement($this->entry1);
        Blitz::$plugin->generateCache->save($this->output, $this->siteUri);

        $refreshData = RefreshDataModel::createFromElement($this->entry1);
        $refreshData->addChangedField($this->entry1, 'text');
        $refreshData->addIsChangedByFields($this->entry1, true);
        $cacheIds = RefreshCacheHelper::getElementCacheIds(
            Entry::class, $refreshData,
        );

        $this->assertCount(0, $cacheIds);

        $refreshData = RefreshDataModel::createFromElement($this->entry1);
        $cacheIds = RefreshCacheHelper::getElementCacheIds(
            Entry::class, $refreshData,
        );

        $this->assertCount(1, $cacheIds);
    }

    public function testGetElementTypeQueryRecords()
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

    public function testGetElementTypeQueryRecordsWithChangedAttribute()
    {
        Blitz::$plugin->generateCache->addElementQuery(
            Entry::find()->title('x')->orderBy('text')
        );
        Blitz::$plugin->generateCache->save($this->output, $this->siteUri);

        $refreshData = RefreshDataModel::createFromElement($this->entry1);
        $refreshData->addIsChangedByAttributes($this->entry1, true);
        $this->_assertElementTypeQueryRecordCount(0, $refreshData);

        $refreshData->addChangedAttribute($this->entry1, 'slug');
        $this->_assertElementTypeQueryRecordCount(0, $refreshData);

        $refreshData->addChangedAttribute($this->entry1, 'title');
        $this->_assertElementTypeQueryRecordCount(1, $refreshData);
    }

    public function testGetElementTypeQueryRecordsWithChangedField()
    {
        Blitz::$plugin->generateCache->addElementQuery(
            Entry::find()->title('x')->orderBy('text')
        );
        Blitz::$plugin->generateCache->save($this->output, $this->siteUri);

        $refreshData = RefreshDataModel::createFromElement($this->entry1);
        $refreshData->addIsChangedByFields($this->entry1, true);
        $this->_assertElementTypeQueryRecordCount(0, $refreshData);

        $refreshData->addChangedField($this->entry1, 'moreText');
        $this->_assertElementTypeQueryRecordCount(0, $refreshData);

        $refreshData->addChangedField($this->entry1, 'text');
        $this->_assertElementTypeQueryRecordCount(1, $refreshData);
    }

    public function testGetElementTypeQueryRecordsWithChangedDateUpdated()
    {
        Blitz::$plugin->generateCache->addElementQuery(
            Entry::find()->orderBy('dateUpdated, text')
        );
        Blitz::$plugin->generateCache->save($this->output, $this->siteUri);

        $refreshData = RefreshDataModel::createFromElement($this->entry1);
        $refreshData->addIsChangedByFields($this->entry1, true);
        $this->_assertElementTypeQueryRecordCount(1, $refreshData);
    }

    private function _assertElementTypeQueryRecordCount(int $count, RefreshDataModel $refreshData)
    {
        $this->assertCount(
            $count,
            RefreshCacheHelper::getElementTypeQueryRecords(Entry::class, $refreshData)
        );
    }
}
