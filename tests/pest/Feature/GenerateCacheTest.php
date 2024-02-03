<?php

/**
 * Tests the saving of cached values, element cache records and element query records.
 */

use craft\commerce\elements\Product;
use craft\db\FixedOrderExpression;
use craft\db\Query;
use craft\elements\Entry;
use craft\fields\data\MultiOptionsFieldData;
use craft\fields\data\OptionData;
use craft\helpers\App;
use craft\helpers\Json;
use craft\helpers\StringHelper;
use craft\mutex\Mutex;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\helpers\FieldHelper;
use putyourlightson\blitz\records\CacheRecord;
use putyourlightson\blitz\records\ElementCacheRecord;
use putyourlightson\blitz\records\ElementFieldCacheRecord;
use putyourlightson\blitz\records\ElementQueryAttributeRecord;
use putyourlightson\blitz\records\ElementQueryCacheRecord;
use putyourlightson\blitz\records\ElementQueryFieldRecord;
use putyourlightson\blitz\records\ElementQueryRecord;
use putyourlightson\blitz\records\ElementQuerySourceRecord;
use putyourlightson\blitz\records\IncludeRecord;
use putyourlightson\blitz\records\SsiIncludeCacheRecord;
use putyourlightson\campaign\elements\CampaignElement;
use putyourlightson\campaign\elements\MailingListElement;

beforeEach(function() {
    Blitz::$plugin->settings->cachingEnabled = true;
    Blitz::$plugin->settings->outputComments = true;
    Blitz::$plugin->generateCache->options->outputComments = null;
    Blitz::$plugin->cacheStorage->deleteAll();
    Blitz::$plugin->flushCache->flushAll();

    $mutex = Mockery::mock(Mutex::class);
    $mutex->shouldReceive('acquire')->andReturn(true);
    $mutex->shouldReceive('release');
    Craft::$app->set('mutex', $mutex);
});

test('Cached value is saved with output comments', function() {
    $output = createOutput();
    $siteUri = createSiteUri();
    Blitz::$plugin->generateCache->save($output, $siteUri);

    expect(Blitz::$plugin->cacheStorage->get($siteUri))
        ->toContain($output, 'Cached by Blitz on');
});

test('Cached value is saved without output comments', function() {
    $output = createOutput();
    $siteUri = createSiteUri();
    Blitz::$plugin->generateCache->options->outputComments = false;
    Blitz::$plugin->generateCache->save($output, $siteUri);

    expect(Blitz::$plugin->cacheStorage->get($siteUri))
        ->toContain($output)
        ->not()->toContain('Cached by Blitz on');
});

test('Cached value is saved with output comments when file extension is ".html"', function() {
    $siteUri = createSiteUri(uri: 'page.html');
    Blitz::$plugin->generateCache->save(createOutput(), $siteUri);

    expect(Blitz::$plugin->cacheStorage->get($siteUri))
        ->toContain('Cached by Blitz on');
});

test('Cached value is saved without output comments when file extension is not `.html`', function() {
    $siteUri = createSiteUri(uri: 'page.json');
    Blitz::$plugin->generateCache->save(createOutput(), $siteUri);

    expect(Blitz::$plugin->cacheStorage->get($siteUri))
        ->not()->toContain('Cached by Blitz on');
});

test('Cache record with max URI length is saved', function() {
    $siteUri = createSiteUri(uri: StringHelper::randomString(Blitz::$plugin->settings->maxUriLength));
    Blitz::$plugin->generateCache->save(createOutput(), $siteUri);
    $count = CacheRecord::find()
        ->where($siteUri->toArray())
        ->count();

    expect($count)
        ->toEqual(1);
});

test('Cache record with max URI length exceeded throws exception', function() {
    $siteUri = createSiteUri(uri: StringHelper::randomString(Blitz::$plugin->settings->maxUriLength + 1));
    Blitz::$plugin->generateCache->save(createOutput(), $siteUri);
})->throws(Exception::class);

test('Element cache record is saved without custom fields', function() {
    $entry = createEntry();
    Blitz::$plugin->generateCache->addElement($entry);
    Blitz::$plugin->generateCache->save(createOutput(), createSiteUri());

    expect(ElementCacheRecord::class)
        ->toHaveRecordCount(1, ['elementId' => $entry->id])
        ->and(ElementFieldCacheRecord::class)
        ->toHaveRecordCount(0, ['elementId' => $entry->id]);
});

test('Element cache record is saved with custom fields', function() {
    $entry = createEntry();
    Blitz::$plugin->generateCache->addElement($entry);
    // Access the fields to register usage
    /** @noinspection PhpUnusedLocalVariableInspection */
    $text = $entry->plainText;
    Blitz::$plugin->generateCache->save(createOutput(), createSiteUri());

    expect(ElementCacheRecord::class)
        ->toHaveRecordCount(1, ['elementId' => $entry->id])
        ->and(ElementFieldCacheRecord::class)
        ->toHaveRecordCount(1, ['elementId' => $entry->id]);
});

test('Element cache record is saved with eager loaded custom fields', function() {
    $entry = Entry::find()->with(['relatedTo'])->one();
    Blitz::$plugin->generateCache->addElement($entry);
    Blitz::$plugin->generateCache->save(createOutput(), createSiteUri());

    expect(ElementCacheRecord::class)
        ->toHaveRecordCount(1, ['elementId' => $entry->id])
        ->and(ElementFieldCacheRecord::class)
        ->toHaveRecordCount(1, ['elementId' => $entry->id]);
});

test('Element cache record is saved with eager loaded custom fields in variable', function() {
    $entry = createEntryWithRelationship();
    Craft::$app->elements->eagerLoadElements(Entry::class, [$entry], ['relatedTo']);
    Blitz::$plugin->generateCache->addElement($entry);
    Blitz::$plugin->generateCache->save(createOutput(), createSiteUri());

    expect(ElementCacheRecord::class)
        ->toHaveRecordCount(1, ['elementId' => $entry->id])
        ->and(ElementFieldCacheRecord::class)
        ->toHaveRecordCount(1, ['elementId' => $entry->id]);
});

test('Element cache record is saved for preloaded single', function() {
    Craft::$app->config->general->preloadSingles = true;
    Craft::$app->view->renderString('{{ single.title }}');
    $entry = Entry::find()->section(App::env('TEST_SINGLE_SECTION_HANDLE'))->one();
    Blitz::$plugin->generateCache->save(createOutput(), createSiteUri());

    expect(ElementCacheRecord::class)
        ->toHaveRecordCount(1, ['elementId' => $entry->id]);
});

test('Element cache record is saved with eager loaded custom fields for preloaded single', function() {
    Craft::$app->config->general->preloadSingles = true;
    $entry = Entry::find()->section(App::env('TEST_SINGLE_SECTION_HANDLE'))->one();
    Craft::$app->view->renderTemplate('test/_eager.twig');
    Blitz::$plugin->generateCache->save(createOutput(), createSiteUri());

    expect(ElementCacheRecord::class)
        ->toHaveRecordCount(1, ['elementId' => $entry->id])
        ->and(ElementFieldCacheRecord::class)
        ->toHaveRecordCount(1, ['elementId' => $entry->id]);
});

test('Element cache records are saved with all statuses for relation field queries', function() {
    $enabledEntry = createEntry();
    $disabledEntry = createEntry(enabled: false);
    $entry = createEntryWithRelationship([$enabledEntry, $disabledEntry]);

    // The entry must be fetched from the DB for the test to work.
    $entry = Entry::find()->id($entry->id)->one();
    $entry->relatedTo->all();

    expect(Blitz::$plugin->generateCache->generateData->getElementIds())
        ->toContain($enabledEntry->id, $disabledEntry->id);
});

test('Element cache records are saved with all statuses for eager loaded relation field queries', function() {
    $enabledEntry = createEntry();
    $disabledEntry = createEntry(enabled: false);
    $entry = createEntryWithRelationship([$enabledEntry, $disabledEntry]);

    Blitz::$plugin->generateCache->reset();

    // The entry must be fetched from the DB for the test to work.
    Entry::find()->id($entry->id)->with('relatedTo')->one();

    expect(Blitz::$plugin->generateCache->generateData->getElementIds())
        ->toContain($enabledEntry->id, $disabledEntry->id);
});

test('Element query records without specific identifiers are saved', function() {
    $elementQuerySets = [
        [
            Entry::find(),
            Entry::find()->limit(''),
            Entry::find()->offset(0),
        ],
        [
            Entry::find()->id('not 1'),
        ],
        [
            Entry::find()->id(['not', 1]),
            Entry::find()->id(['not', '1']),
        ],
        [
            Entry::find()->slug('not slug'),
        ],
        [
            Entry::find()->slug(['not', 'slug']),
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

    foreach ($elementQuerySets as $elementQuerySet) {
        foreach ($elementQuerySet as $elementQuery) {
            Blitz::$plugin->generateCache->addElementQuery($elementQuery);
        }
    }

    expect(ElementQueryRecord::class)
        ->toHaveRecordCount(count($elementQuerySets));
});

test('Element query records with specific identifiers are not saved', function() {
    $elementQueries = [
        Entry::find()->id(1),
        Entry::find()->id('1'),
        Entry::find()->id('1, 2, 3'),
        Entry::find()->id([1, 2, 3]),
        Entry::find()->id(['1', '2', '3']),
        Entry::find()->slug('slug'),
        Entry::find()->slug(['slug']),
        Entry::find()->slug([null, 'slug']),
        Entry::find()->orderBy('RAND()'),
        Entry::find()->orderBy('Rand(123)'),
    ];

    foreach ($elementQueries as $elementQuery) {
        Blitz::$plugin->generateCache->addElementQuery($elementQuery);
    }

    expect(ElementQueryRecord::class)
        ->toHaveRecordCount(0);
});

test('Element query record with join is saved', function() {
    $elementQuery = Entry::find()->innerJoin('{{%users}}');
    Blitz::$plugin->generateCache->addElementQuery($elementQuery);

    expect(ElementQueryRecord::class)
        ->toHaveRecordCount(1);
});

test('Element query record with relation field is not saved', function() {
    $entry = createEntryWithRelationship();
    ElementQueryRecord::deleteAll();
    $elementQuery = $entry->relatedTo;
    Blitz::$plugin->generateCache->addElementQuery($elementQuery);

    expect(ElementQueryRecord::class)
        ->toHaveRecordCount(0);
});

test('Element query record with related to param is saved', function() {
    $elementQuery = Entry::find()->relatedTo(1);
    Blitz::$plugin->generateCache->addElementQuery($elementQuery);

    expect(ElementQueryRecord::class)
        ->toHaveRecordCount(1);
});

test('Element query record with query param is saved without the param', function() {
    $elementQuery = Entry::find();
    $elementQuery->query = new Query();
    Blitz::$plugin->generateCache->addElementQuery($elementQuery);

    /** @var ElementQueryRecord $record */
    $record = ElementQueryRecord::find()->one();
    $params = Json::decodeIfJson($record->params);

    expect($params['query'] ?? null)
        ->toBeNull();
});

test('Element query record with expression is not saved', function() {
    $expression = new FixedOrderExpression('elements.id', [], Craft::$app->db);
    $elementQuery = Entry::find()->orderBy($expression);
    Blitz::$plugin->generateCache->addElementQuery($elementQuery);

    expect(ElementQueryRecord::class)
        ->toHaveRecordCount(0);
});

test('Element query record with option field data is converted to value', function() {
    $optionFieldData = new OptionData('One', 1, true);
    $elementQuery = Entry::find()->dropdown($optionFieldData);
    Blitz::$plugin->generateCache->addElementQuery($elementQuery);

    /** @var ElementQueryRecord $record */
    $record = ElementQueryRecord::find()->one();
    $params = Json::decodeIfJson($record->params);

    expect($params['dropdown'])
        ->toEqual(1);
});

test('Element query record with multi options field data is converted to array of values', function() {
    $optionFieldData = new MultiOptionsFieldData();
    $optionFieldData->setOptions([
        new OptionData('One', 1, true),
        new OptionData('Two', 2, false),
    ]);
    $elementQuery = Entry::find()->multiSelect($optionFieldData);
    Blitz::$plugin->generateCache->addElementQuery($elementQuery);

    /** @var ElementQueryRecord $record */
    $record = ElementQueryRecord::find()->one();
    $params = Json::decodeIfJson($record->params);

    expect($params['multiSelect'])
        ->toEqual([1, 2]);
});

test('Element query cache records are saved', function() {
    $elementQuery = Entry::find();
    Blitz::$plugin->generateCache->addElementQuery($elementQuery);
    Blitz::$plugin->generateCache->save(createOutput(), createSiteUri());
    Blitz::$plugin->generateCache->addElementQuery($elementQuery);
    Blitz::$plugin->generateCache->save(createOutput(), createSiteUri(uri: 'page-new'));

    expect(ElementQueryCacheRecord::class)
        ->toHaveRecordCount(2);
});

test('Element query source records with specific source identifiers are saved', function() {
    $elementQueries = [
        Entry::find(),
        Entry::find()->sectionId(1),
        Entry::find()->sectionId([1, 2, 3]),
        Product::find()->typeId(4),
        CampaignElement::find()->campaignTypeId(5),
        MailingListElement::find()->mailingListTypeId(6),
    ];

    foreach ($elementQueries as $elementQuery) {
        Blitz::$plugin->generateCache->addElementQuery($elementQuery);
    }

    $count = ElementQueryRecord::find()
        ->joinWith('elementQuerySources', false)
        ->where(['not', ['sourceId' => null]])
        ->count();

    expect($count)
        ->toEqual(7);

    $sourceIds = ElementQuerySourceRecord::find()
        ->select('sourceId')
        ->column();

    expect($sourceIds)
        ->toEqual([1, 1, 2, 3, 4, 5, 6]);
});

test('Element query source records without specific source identifiers are not saved', function() {
    $elementQueries = [
        Entry::find()->sectionId('not 1'),
        Entry::find()->sectionId('> 1'),
        Entry::find()->sectionId(['not', 1]),
        Entry::find()->sectionId(['not', '1']),
        Entry::find()->sectionId(['>', '1']),
    ];

    foreach ($elementQueries as $elementQuery) {
        Blitz::$plugin->generateCache->addElementQuery($elementQuery);
    }

    expect(ElementQuerySourceRecord::class)
        ->toHaveRecordCount(0);
});

test('Element query attribute records are saved', function() {
    $elementQuery = Entry::find()->title('x');
    Blitz::$plugin->generateCache->addElementQuery($elementQuery);
    $attributes = ElementQueryAttributeRecord::find()
        ->select('attribute')
        ->column();

    expect($attributes)
        ->toEqual(['postDate', 'title']);
});

test('Element query attribute records are saved with order by', function() {
    $elementQuery = Entry::find()->orderBy('title');
    Blitz::$plugin->generateCache->addElementQuery($elementQuery);
    $attributes = ElementQueryAttributeRecord::find()
        ->select('attribute')
        ->column();

    expect($attributes)
        ->toEqual(['title']);
});

test('Element query attribute records are saved with order by parts array', function() {
    $elementQuery = Entry::find()->orderBy(['entries.title' => SORT_DESC]);
    Blitz::$plugin->generateCache->addElementQuery($elementQuery);
    $attributes = ElementQueryAttributeRecord::find()
        ->select('attribute')
        ->column();

    expect($attributes)
        ->toEqual(['title']);
});

test('Element query attribute records are saved with before', function() {
    $elementQuery = Entry::find()
        ->before('1999-12-31')
        ->orderBy('title');
    Blitz::$plugin->generateCache->addElementQuery($elementQuery);
    $attributes = ElementQueryAttributeRecord::find()
        ->select('attribute')
        ->column();

    expect($attributes)
        ->toEqual(['postDate', 'title']);
});

test('Element query field records are saved with order by', function() {
    $elementQuery = Entry::find()->orderBy('plainText');
    Blitz::$plugin->generateCache->addElementQuery($elementQuery);
    $fieldIds = ElementQueryFieldRecord::find()
        ->select('fieldId')
        ->column();

    expect($fieldIds)
        ->toEqual(FieldHelper::getFieldIdsFromHandles(['plainText']));
});

test('Element query field records are saved with order by array', function() {
    $elementQuery = Entry::find()->orderBy(['plainText' => SORT_ASC]);
    Blitz::$plugin->generateCache->addElementQuery($elementQuery);
    $fieldIds = ElementQueryFieldRecord::find()
        ->select('fieldId')
        ->column();

    expect($fieldIds)
        ->toEqual(FieldHelper::getFieldIdsFromHandles(['plainText']));
});

test('Cache tags are saved', function() {
    $tags = ['tag1', 'tag2', 'tag3'];
    Blitz::$plugin->generateCache->options->tags = $tags;
    Blitz::$plugin->generateCache->save(createOutput(), createSiteUri());

    expect(Blitz::$plugin->cacheTags->getCacheIds($tags))
        ->toHaveCount(1);
});

test('Include record is saved', function() {
    IncludeRecord::deleteAll();
    Blitz::$plugin->generateCache->saveInclude(1, 't', []);

    expect(IncludeRecord::class)
        ->toHaveRecordCount(1);
});

test('SSI include cache record is saved', function() {
    [$includeId] = Blitz::$plugin->generateCache->saveInclude(1, 't', []);
    Blitz::$plugin->generateCache->addSsiInclude($includeId);
    Blitz::$plugin->generateCache->save(createOutput(), createSiteUri());

    expect(SsiIncludeCacheRecord::class)
        ->toHaveRecordCount(1);
});
