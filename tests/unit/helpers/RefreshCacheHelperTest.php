<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitztests\unit\helpers;

use Codeception\Test\Unit;
use craft\elements\Entry;
use putyourlightson\blitz\behaviors\ElementChangedBehavior;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\helpers\FieldHelper;
use putyourlightson\blitz\helpers\RefreshCacheHelper;
use putyourlightson\blitz\models\RefreshDataModel;
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
     * @var RefreshDataModel
     */
    private RefreshDataModel $refreshData;

    /**
     * @var Entry|ElementChangedBehavior
     */
    private Entry|ElementChangedBehavior $entry;

    protected function _before()
    {
        parent::_before();

        $this->refreshData = new RefreshDataModel();
        $this->entry = Entry::find()->sectionId(1)->one();
    }

    public function testGetElementTypeQueryRecords()
    {
        Blitz::$plugin->generateCache->addElementQuery(
            Entry::find()
        );

        $this->refreshData->addElement($this->entry);
        $this->_assertElementTypeQueryRecordCount(1);
    }

    public function testGetElementTypeQueryRecordsWithChangedAttribute()
    {
        Blitz::$plugin->generateCache->addElementQuery(
            Entry::find()->title('x')->text('z')
        );

        $this->refreshData->addElement($this->entry);
        $this->refreshData->addIsChangedByAttributes($this->entry, true);
        $this->_assertElementTypeQueryRecordCount(0);

        $this->refreshData->addChangedAttributes($this->entry, ['slug']);
        $this->_assertElementTypeQueryRecordCount(0);

        $this->refreshData->addChangedAttributes($this->entry, ['title']);
        $this->_assertElementTypeQueryRecordCount(1);

        $this->refreshData->addChangedAttributes($this->entry, ['title', 'slug']);
        $this->_assertElementTypeQueryRecordCount(1);
    }

    public function testGetElementTypeQueryRecordsWithChangedAttributes()
    {
        Blitz::$plugin->generateCache->addElementQuery(
            Entry::find()->title('x')->slug('y')->text('z')
        );

        $this->refreshData->addElement($this->entry);
        $this->refreshData->addIsChangedByAttributes($this->entry, true);
        $this->_assertElementTypeQueryRecordCount(0);

        $this->refreshData->addChangedAttributes($this->entry, ['slug']);
        $this->_assertElementTypeQueryRecordCount(1);

        $this->refreshData->addChangedAttributes($this->entry, ['slug']);
        $this->_assertElementTypeQueryRecordCount(1);

        $this->refreshData->addChangedAttributes($this->entry, ['title']);
        $this->_assertElementTypeQueryRecordCount(1);

        $this->refreshData->addChangedAttributes($this->entry, ['title', 'slug']);
        $this->_assertElementTypeQueryRecordCount(1);
    }

    public function testGetElementTypeQueryRecordsWithChangedFields()
    {
        Blitz::$plugin->generateCache->addElementQuery(
            Entry::find()->title('x')->text('z')
        );

        $this->refreshData->addElement($this->entry);
        $this->refreshData->addIsChangedByFields($this->entry, true);
        $this->_assertElementTypeQueryRecordCount(0);

        $this->refreshData->addChangedFields($this->entry, FieldHelper::getFieldIdsFromHandles(['text']));
        $this->_assertElementTypeQueryRecordCount(1);
    }

    public function testGetElementTypeQueryRecordsWithAllChangedFields()
    {
        Blitz::$plugin->generateCache->addElementQuery(
            Entry::find()->title('x')
        );

        $this->refreshData->addElement($this->entry);
        $this->refreshData->addChangedFields($this->entry, true);
        $this->_assertElementTypeQueryRecordCount(0);

        Blitz::$plugin->generateCache->addElementQuery(
            Entry::find()->title('x')->text('z')
        );
        $this->_assertElementTypeQueryRecordCount(1);
    }

    private function _assertElementTypeQueryRecordCount(int $count)
    {
        $this->assertCount(
            $count,
            RefreshCacheHelper::getElementTypeQueryRecords(Entry::class, $this->refreshData)
        );
    }

}
