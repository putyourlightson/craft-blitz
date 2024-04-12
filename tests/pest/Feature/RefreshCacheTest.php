<?php

/**
 * Tests the tracking of changes to elements and the resulting element cache IDs and element query type records.
 */

use craft\elements\Asset;
use craft\elements\Entry;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\helpers\RefreshCacheHelper;
use putyourlightson\blitz\models\RefreshDataModel;
use putyourlightson\blitz\records\ElementQueryRecord;
use putyourlightson\blitz\records\ElementQuerySourceRecord;

beforeEach(function() {
    Blitz::$plugin->cacheStorage->deleteAll();
    Blitz::$plugin->flushCache->flushAll(true);
    Blitz::$plugin->generateCache->options->cachingEnabled = true;
    Blitz::$plugin->refreshCache->batchMode = true;
    Blitz::$plugin->refreshCache->reset();
    Blitz::$plugin->settings->refreshCacheWhenElementSavedUnchanged = false;
    Blitz::$plugin->settings->refreshCacheWhenElementSavedNotLive = false;
});

test('Element is not tracked when it is unchanged', function() {
    $entry = createEntry();
    Blitz::$plugin->refreshCache->addElement($entry);

    expect($entry)
        ->toNotBeTracked();
});

test('Element is tracked when `refreshCacheWhenElementSavedUnchanged` is `true` and it is unchanged', function() {
    $entry = createEntry();
    Blitz::$plugin->settings->refreshCacheWhenElementSavedUnchanged = true;
    Blitz::$plugin->refreshCache->addElement($entry);

    expect($entry)
        ->toBeTracked();
});

test('Element is not tracked when disabled and its attribute is changed', function() {
    $entry = createEntry(enabled: false);
    $entry->title = 'Title123';
    Blitz::$plugin->refreshCache->addElement($entry);

    expect($entry)
        ->toNotBeTracked();
});

test('Element is tracked when disabled and `refreshCacheWhenElementSavedNotLive` is `true` and its attribute is changed', function() {
    $entry = createEntry(enabled: false);
    Blitz::$plugin->settings->refreshCacheWhenElementSavedNotLive = true;
    $entry->title = 'Title123';
    Blitz::$plugin->refreshCache->addElement($entry);

    expect($entry)
        ->toBeTracked('attributes', ['title']);
});

test('Element is tracked when its status is changed', function() {
    $entry = createEntry();
    $entry->enabled = false;
    Blitz::$plugin->refreshCache->addElement($entry);

    expect($entry)
        ->toBeTracked();
});

test('Element is tracked when it expires', function() {
    $entry = createEntry();
    $entry->expiryDate = new DateTime('20010101');
    Blitz::$plugin->refreshCache->addElement($entry);

    expect($entry)
        ->toBeTracked();
});

test('Element is tracked when it is deleted', function() {
    $entry = createEntry();
    Craft::$app->getElements()->deleteElement($entry);

    expect($entry)
        ->toBeTracked();
});

test('Element is tracked when its attribute is changed', function() {
    $entry = createEntry();
    $entry->title = 'Title123';
    Blitz::$plugin->refreshCache->addElement($entry);

    expect($entry)
        ->toBeTracked('attributes', ['title']);
});

test('Element is tracked when its field is changed', function() {
    $entry = createEntry();
    $entry->plainText = 'Text123';
    Blitz::$plugin->refreshCache->addElement($entry);

    expect($entry)
        ->toBeTracked('fields', [], ['plainText']);
});

test('Element is tracked when its attribute and field are changed', function() {
    $entry = createEntry();
    $entry->title = 'Title123';
    $entry->plainText = 'Text123';
    Blitz::$plugin->refreshCache->addElement($entry);

    expect($entry)
        ->toBeTracked('attributes', ['title'], ['plainText']);
});

test('Element is tracked when its status and attribute and field are changed', function() {
    $entry = createEntry();
    $entry->enabled = false;
    $entry->title = 'Title123';
    $entry->plainText = 'Text123';
    Blitz::$plugin->refreshCache->addElement($entry);

    expect($entry)
        ->toBeTracked('', ['title'], ['plainText']);
});

test('Asset is tracked when its file is replaced', function() {
    $asset = createAsset();
    $asset->scenario = Asset::SCENARIO_REPLACE;
    Blitz::$plugin->refreshCache->addElement($asset);

    expect($asset)
        ->toBeChangedByFile();
});

test('Asset is tracked when its filename is changed', function() {
    $asset = createAsset();
    $asset->filename = 'new-filename.jpg';
    Blitz::$plugin->refreshCache->addElement($asset);

    expect($asset)
        ->toBeChangedByFile();
});

test('Asset is tracked when its focal point is changed', function() {
    $asset = createAsset();
    $asset->setFocalPoint([
        'x' => 101,
        'y' => 102,
    ]);
    Blitz::$plugin->refreshCache->addElement($asset);

    expect($asset)
        ->toBeChangedByFile();
});

test('Element expiry date record is saved when an entry has a future post date', function() {
    $entry = createEntry();
    $entry->postDate = (new DateTime('now'))->add(new DateInterval('P2D'));
    Blitz::$plugin->refreshCache->addElementExpiryDates($entry);

    expect($entry)
        ->toExpireOn($entry->postDate);
});

test('Element expiry date record is saved when an entry has a future expiry date', function() {
    $entry = createEntry();
    $entry->expiryDate = (new DateTime('now'))->add(new DateInterval('P2D'));
    Blitz::$plugin->refreshCache->addElementExpiryDates($entry);

    expect($entry)
        ->toExpireOn($entry->expiryDate);
});

test('Element cache IDs are returned when an entry is changed', function() {
    $entry = createEntry();
    Blitz::$plugin->generateCache->addElement($entry);
    Blitz::$plugin->generateCache->save(createOutput(), createSiteUri());
    $refreshData = RefreshDataModel::createFromElement($entry);

    expect(RefreshCacheHelper::getElementCacheIds(Entry::class, $refreshData))
        ->toHaveCount(1);
});

test('Element cache IDs are returned when an entry is changed by attributes', function() {
    $entry = createEntry();
    Blitz::$plugin->generateCache->addElement($entry);
    Blitz::$plugin->generateCache->save(createOutput(), createSiteUri());
    $refreshData = RefreshDataModel::createFromElement($entry);
    $refreshData->addChangedAttribute($entry, 'title');
    $refreshData->addIsChangedByAttributes($entry, true);

    expect(RefreshCacheHelper::getElementCacheIds(Entry::class, $refreshData))
        ->toHaveCount(1);
});

test('Element cache IDs are not returned when an entry is changed by custom fields', function() {
    $entry = createEntry();
    Blitz::$plugin->generateCache->addElement($entry);
    Blitz::$plugin->generateCache->save(createOutput(), createSiteUri());
    $refreshData = RefreshDataModel::createFromElement($entry);
    $refreshData->addChangedField($entry, 'plainText');
    $refreshData->addIsChangedByFields($entry, true);

    expect(RefreshCacheHelper::getElementCacheIds(Entry::class, $refreshData))
        ->toHaveCount(0);
});

test('Element query cache IDs are returned when a disabled entry is changed', function() {
    $entry = createEntry(enabled: false);
    Blitz::$plugin->generateCache->addElementQuery(Entry::find());
    Blitz::$plugin->generateCache->save(createOutput(), createSiteUri());
    $refreshData = RefreshDataModel::createFromElement($entry);
    $elementQueryRecord = ElementQueryRecord::find()->orderBy(['id' => SORT_DESC])->one();

    expect(RefreshCacheHelper::getElementQueryCacheIds($elementQueryRecord, $refreshData))
        ->toHaveCount(1);
});

test('Element query type records are returned when an entry is changed', function() {
    $entry = createEntry();
    Blitz::$plugin->generateCache->addElementQuery(Entry::find());
    Blitz::$plugin->generateCache->addElementQuery(Entry::find()->sectionId($entry->sectionId));
    Blitz::$plugin->generateCache->save(createOutput(), createSiteUri());
    $refreshData = RefreshDataModel::createFromElement($entry);

    expect(RefreshCacheHelper::getElementTypeQueryRecords(Entry::class, $refreshData))
        ->toHaveCount(2);
});

test('Element query type records without a cache ID are not returned when an entry is changed', function() {
    $entry = createEntry();
    $refreshData = RefreshDataModel::createFromElement($entry);

    // Add a rogue element query (without a cache ID)
    Craft::$app->db->createCommand()->insert(ElementQueryRecord::tableName(), [
        'index' => 1234567890,
        'type' => Entry::class,
        'params' => '[]',
    ])->execute();
    $queryId = Craft::$app->db->getLastInsertID();

    // Add a source ID
    Craft::$app->db->createCommand()->insert(ElementQuerySourceRecord::tableName(), [
        'queryId' => $queryId,
        'sourceId' => $entry->sectionId,
    ])->execute();

    expect(RefreshCacheHelper::getElementTypeQueryRecords(Entry::class, $refreshData))
        ->toHaveCount(0);
});

test('Element query type records are returned when an entry is changed by attributes used in the query', function() {
    $entry = createEntry();
    Blitz::$plugin->generateCache->addElementQuery(Entry::find()->title('xyz'));
    Blitz::$plugin->generateCache->save(createOutput(), createSiteUri());
    $refreshData = RefreshDataModel::createFromElement($entry);
    $refreshData->addChangedAttribute($entry, 'title');
    $refreshData->addIsChangedByAttributes($entry, true);

    expect(RefreshCacheHelper::getElementTypeQueryRecords(Entry::class, $refreshData))
        ->toHaveCount(1);
});

test('Element query type records are not returned when an entry is changed by attributes not used in the query', function() {
    $entry = createEntry();
    Blitz::$plugin->generateCache->addElementQuery(Entry::find()->title('xyz'));
    Blitz::$plugin->generateCache->save(createOutput(), createSiteUri());
    $refreshData = RefreshDataModel::createFromElement($entry);
    $refreshData->addIsChangedByAttributes($entry, true);

    expect(RefreshCacheHelper::getElementTypeQueryRecords(Entry::class, $refreshData))
        ->toHaveCount(0);
});

test('Element query type records are returned when an entry is changed by custom fields used in the query', function() {
    $entry = createEntry();
    Blitz::$plugin->generateCache->addElementQuery(Entry::find()->orderBy(['plainText']));
    Blitz::$plugin->generateCache->save(createOutput(), createSiteUri());
    $refreshData = RefreshDataModel::createFromElement($entry);
    $refreshData->addChangedField($entry, 'plainText');
    $refreshData->addIsChangedByFields($entry, true);

    expect(RefreshCacheHelper::getElementTypeQueryRecords(Entry::class, $refreshData))
        ->toHaveCount(1);
});

test('Element query type records are not returned when an entry is changed by custom fields not used in the query', function() {
    $entry = createEntry();
    Blitz::$plugin->generateCache->addElementQuery(Entry::find()->orderBy(['plainText']));
    Blitz::$plugin->generateCache->save(createOutput(), createSiteUri());
    $refreshData = RefreshDataModel::createFromElement($entry);
    $refreshData->addIsChangedByFields($entry, true);

    expect(RefreshCacheHelper::getElementTypeQueryRecords(Entry::class, $refreshData))
        ->toHaveCount(0);
});

test('Element query type records are returned when an entry is changed with the date updated used in the query', function() {
    $entry = createEntry();
    Blitz::$plugin->generateCache->addElementQuery(Entry::find()->orderBy(['dateUpdated']));
    Blitz::$plugin->generateCache->save(createOutput(), createSiteUri());
    $refreshData = RefreshDataModel::createFromElement($entry);
    $refreshData->addIsChangedByFields($entry, true);

    expect(RefreshCacheHelper::getElementTypeQueryRecords(Entry::class, $refreshData))
        ->toHaveCount(1);
});

test('Element query type records are deleted when executing them results in an exception', function() {
    Blitz::$plugin->generateCache->addElementQuery(Entry::find()->ancestorOf(999999999999999));

    // Disable tracking of element queries, so it doesn’t mess up our test!
    Blitz::$plugin->generateCache->options->trackElementQueries = false;

    $elementQueryRecord = ElementQueryRecord::find()->one();
    RefreshCacheHelper::getElementQueryCacheIds($elementQueryRecord, new RefreshDataModel());

    expect(ElementQueryRecord::find()->count())
        ->toBe(0);
});
