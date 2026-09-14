<?php

use craft\base\Field;
use craft\elements\Entry;
use craft\helpers\Json;
use markhuot\craftpest\test\TestCase;
use putyourlightson\blitz\behaviors\ElementChangedBehavior;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\drivers\storage\DummyStorage;
use putyourlightson\blitz\helpers\DiagnosticsHelper;
use putyourlightson\blitz\helpers\RefreshCacheHelper;
use putyourlightson\blitz\helpers\SiteUriHelper;
use putyourlightson\blitz\jobs\RefreshCacheJob;
use putyourlightson\blitz\models\BaseDataModel;
use putyourlightson\blitz\models\RefreshDataModel;
use putyourlightson\blitz\records\CacheRecord;
use putyourlightson\blitz\records\ElementCacheRecord;
use putyourlightson\blitz\records\ElementFieldCacheRecord;
use putyourlightson\blitz\records\ElementQueryRecord;
use putyourlightson\blitz\records\ElementQuerySiteRecord;
use putyourlightson\blitz\services\CacheRequestService;
use putyourlightson\blitz\services\RefreshCacheService;
use yii\base\Event;

// These site-aware tests use an already migrated multisite installation and roll back all database changes.
// Do not apply unrelated project config or invoke the feature suite's destructive fixture cleanup.
class SiteAwareTestCase extends TestCase
{
    public function createApplication()
    {
        if ($this->needsRequireStatements()) {
            // Booting the test app must not serve an existing cached homepage and exit PHP.
            $handler = function($event) {
                $event->isValid = false;
            };
            Event::on(CacheRequestService::class, CacheRequestService::EVENT_IS_CACHEABLE_REQUEST, $handler);
            try {
                $this->requireCraft();
            } finally {
                Event::off(CacheRequestService::class, CacheRequestService::EVENT_IS_CACHEABLE_REQUEST, $handler);
            }
        }

        return Craft::$app;
    }
}

uses(SiteAwareTestCase::class);

beforeEach(function() {
    $this->transaction = Craft::$app->getDb()->beginTransaction();
    $this->cacheStorage = Blitz::$plugin->cacheStorage;
    $this->settings = Blitz::$plugin->settings->getAttributes();
    Blitz::$plugin->set('cacheStorage', new DummyStorage());
    Blitz::$plugin->settings->cachingEnabled = true;
    Blitz::$plugin->settings->refreshCacheWhenElementSavedUnchanged = false;
    Blitz::$plugin->settings->refreshMode = 0;
    Blitz::$plugin->generateCache->reset();
    Blitz::$plugin->refreshCache->reset();
    $this->entry = createEntry();
    $this->otherSiteId = array_values(array_diff(Craft::$app->getSites()->getAllSiteIds(), [$this->entry->siteId]))[0];
    $this->other = Entry::find()->id($this->entry->id)->siteId($this->otherSiteId)->status(null)->one();
    expect($this->other)->not->toBeNull();
});

afterEach(function() {
    Blitz::$plugin->refreshCache->reset();
    Blitz::$plugin->generateCache->reset();
    $this->transaction->rollBack();
    Blitz::$plugin->set('cacheStorage', $this->cacheStorage);
    Blitz::$plugin->settings->setAttributes($this->settings, false);
    Blitz::$plugin->generateCache->reset();
});

function siteAwareCache(Entry $entry, string $uri, ?int $pageSiteId = null, ?string $field = null): int
{
    $generate = Blitz::$plugin->generateCache;
    $generate->reset();
    $generate->addElement($entry);
    if ($field !== null) {
        $generate->generateData->addElementTrackField($entry, $field);
    }
    $siteUri = createSiteUri($pageSiteId ?? $entry->siteId, $uri);
    $generate->save('Site-aware test', $siteUri);

    return (int)CacheRecord::find()->select(['id'])->where($siteUri->toArray())->scalar();
}

test('SiteAware translated title refreshes its variant and cross-site dependents', function() {
    $own = siteAwareCache($this->entry, 'site-aware/own');
    $other = siteAwareCache($this->other, 'site-aware/other');
    $crossSite = siteAwareCache($this->entry, 'site-aware/cross', $this->otherSiteId);
    $this->entry->title = 'A changed title';
    expect(Craft::$app->getElements()->saveElement($this->entry))->toBeTrue();
    $data = Blitz::$plugin->refreshCache->refreshData;

    expect($data->getElementSiteIds(Entry::class, $this->entry->id))->toBe([$this->entry->siteId]);
    $cacheIds = RefreshCacheHelper::getElementCacheIds(Entry::class, $data);
    expect($cacheIds)->toContain($own, $crossSite)->not->toContain($other);
    $siteUris = SiteUriHelper::getElementSiteUris([$this->entry->id], $data->getElementSiteIds(Entry::class, $this->entry->id));
    expect(array_unique(array_map(fn($uri) => $uri->siteId, $siteUris)))->toEqual([$this->entry->siteId]);
});

test('SiteAware storage distinguishes fields read from different variants', function() {
    $generate = Blitz::$plugin->generateCache;
    $generate->addElement($this->entry);
    $generate->addElement($this->other);
    $generate->generateData->addElementTrackField($this->entry, 'plainText');
    $generate->save('Site-aware fields', createSiteUri($this->entry->siteId, 'site-aware/fields'));
    $cacheId = (int)CacheRecord::find()->select(['id'])->where(['uri' => 'site-aware/fields'])->scalar();
    expect(ElementCacheRecord::find()->where(['cacheId' => $cacheId])->count())->toEqual(2)
        ->and(ElementFieldCacheRecord::find()->select(['siteId'])->where(['cacheId' => $cacheId])->column())->toEqual([$this->entry->siteId]);

    $data = new RefreshDataModel();
    $data->addElementId(Entry::class, $this->entry->id, [$this->otherSiteId]);
    $data->addChangedFieldHandle($this->entry, 'plainText');
    $data->addIsChangedByFields($this->entry, true);
    expect(RefreshCacheHelper::getElementCacheIds(Entry::class, $data))->not->toContain($cacheId);
});

test('SiteAware query matching respects the queried variant rather than the cached page site', function() {
    $records = [];
    foreach ([$this->entry->siteId, $this->otherSiteId] as $siteId) {
        $generate = Blitz::$plugin->generateCache;
        $generate->reset();
        $query = Entry::find()->siteId($siteId)->sectionId($this->entry->sectionId)->title($this->entry->title);
        $generate->saveElementQuery($query);
        $record = ElementQueryRecord::find()->orderBy(['id' => SORT_DESC])->one();
        $generate->save('Site-aware query', createSiteUri($this->otherSiteId, 'site-aware/query-' . $siteId));
        $records[$siteId] = $record;
    }
    $data = new RefreshDataModel();
    $data->addElementId(Entry::class, $this->entry->id, [$this->entry->siteId]);
    $data->addSourceId(Entry::class, $this->entry->sectionId);
    $queryRecords = RefreshCacheHelper::getElementTypeQueryRecords(Entry::class, $data);
    expect($queryRecords)->toHaveCount(1)
        ->and($queryRecords[0]->id)->toBe($records[$this->entry->siteId]->id)
        ->and(ElementQuerySiteRecord::find()->select(['siteId'])->where(['queryId' => $records[$this->entry->siteId]->id])->column())->toBe([$this->entry->siteId])
        ->and(ElementQuerySiteRecord::find()->select(['siteId'])->where(['queryId' => $records[$this->otherSiteId]->id])->column())->toBe([$this->otherSiteId]);
    expect(RefreshCacheHelper::getElementQueryCacheIds($records[$this->entry->siteId], $data))->toHaveCount(1);

    // Use an invalid order column to prove that the other-site query is not executed.
    $otherSiteRecord = $records[$this->otherSiteId];
    $params = Json::decode($otherSiteRecord->params);
    $params['orderBy'] = ['invalidColumn' => SORT_ASC];
    $otherSiteRecord->params = Json::encode($params);
    $otherSiteRecord->save(false);
    expect(RefreshCacheHelper::getElementQueryCacheIds($otherSiteRecord, $data))->toBeEmpty()
        ->and(ElementQueryRecord::findOne($otherSiteRecord->id))->not->toBeNull();
});

test('SiteAware shared fields and global status changes retain all affected variants', function() {
    $field = $this->entry->getFieldLayout()->getFieldByHandle('plainText');
    $method = $field->translationMethod;
    try {
        $field->translationMethod = Field::TRANSLATION_METHOD_NONE;
        $behavior = $this->entry->getBehavior(ElementChangedBehavior::BEHAVIOR_NAME);
        $behavior->changedFieldsHandles = ['plainText'];
        $behavior->isChangedByFields = true;
        $sites = $behavior->getAffectedSiteIds();
        expect($sites)->toContain($this->entry->siteId, $this->otherSiteId);
        $this->entry->enabled = false;
        expect($behavior->getAffectedSiteIds())->toBeNull();
    } finally {
        $field->translationMethod = $method;
    }
});

test('SiteAware legacy rows and legacy refresh jobs remain conservative', function() {
    $cacheId = siteAwareCache($this->other, 'site-aware/legacy');
    Craft::$app->getDb()->createCommand()->update(ElementCacheRecord::tableName(), ['siteId' => BaseDataModel::SITE_ID_ANY], ['cacheId' => $cacheId])->execute();
    $data = new RefreshDataModel();
    $data->addElementId(Entry::class, $this->entry->id, [$this->entry->siteId]);
    expect(RefreshCacheHelper::getElementCacheIds(Entry::class, $data))->toContain($cacheId);
    $legacy = RefreshDataModel::createFromData(['cacheIds' => [], 'elements' => [Entry::class => ['elementIds' => [$this->entry->id => true]]]]);
    $legacy->addElementId(Entry::class, $this->entry->id, [$this->entry->siteId]);
    expect($legacy->getElementSiteIds(Entry::class, $this->entry->id))->toBeNull();
});

test('SiteAware translated field saves and the refresh job exclude untouched variants', function() {
    $field = $this->entry->getFieldLayout()->getFieldByHandle('plainText');
    $method = $field->translationMethod;
    $siteUris = [];
    $handler = function($event) use (&$siteUris) {
        $siteUris = $event->siteUris;
        $event->isValid = false;
    };
    $refresh = Blitz::$plugin->refreshCache;
    $refresh->on(RefreshCacheService::EVENT_BEFORE_REFRESH_CACHE, $handler);
    try {
        $field->translationMethod = Field::TRANSLATION_METHOD_SITE;
        $own = siteAwareCache($this->entry, 'site-aware/field-own', field: 'plainText');
        $other = siteAwareCache($this->other, 'site-aware/field-other', field: 'plainText');
        $cross = siteAwareCache($this->entry, 'site-aware/field-cross', $this->otherSiteId, 'plainText');
        $this->entry->setFieldValue('plainText', 'A changed localized value');
        expect(Craft::$app->getElements()->saveElement($this->entry))->toBeTrue();
        $data = $refresh->refreshData;
        expect($data->getElementSiteIds(Entry::class, $this->entry->id))->toBe([$this->entry->siteId]);
        expect(RefreshCacheHelper::getElementCacheIds(Entry::class, $data))->toContain($own, $cross)->not->toContain($other);
        $job = new RefreshCacheJob(['data' => $data->data]);
        $job->execute(Blitz::$plugin->queue);
        $uris = array_map(fn($uri) => $uri->siteId . ':' . $uri->uri, $siteUris);
        expect($uris)->toContain($this->entry->siteId . ':site-aware/field-own', $this->otherSiteId . ':site-aware/field-cross')
            ->not->toContain($this->otherSiteId . ':site-aware/field-other', $this->otherSiteId . ':' . $this->other->uri);
    } finally {
        $field->translationMethod = $method;
        $refresh->off(RefreshCacheService::EVENT_BEFORE_REFRESH_CACHE, $handler);
    }
});

test('SiteAware ID queries retain explicit and wildcard site dependencies', function() {
    $generate = Blitz::$plugin->generateCache;
    $generate->addElementQuery(Entry::find()->id($this->entry->id)->siteId($this->otherSiteId));
    expect($generate->generateData->getElementSiteIds())->toBe([$this->entry->id => [$this->otherSiteId]]);
    $generate->reset();
    $generate->addElementQuery(Entry::find()->id($this->entry->id)->site('*'));
    expect($generate->generateData->getElementSiteIds()[$this->entry->id])->toContain($this->entry->siteId, $this->otherSiteId);
});

test('SiteAware multi-site and timestamp queries still refresh when affected', function() {
    $queries = [
        [Entry::find()->site('*'), [$this->entry->siteId, $this->otherSiteId]],
        [Entry::find()->siteId($this->otherSiteId)->orderBy(['dateUpdated' => SORT_DESC]), [BaseDataModel::SITE_ID_ANY]],
    ];
    foreach ($queries as [$query, $expectedSiteIds]) {
        $generate = Blitz::$plugin->generateCache;
        $generate->reset();
        $query->sectionId($this->entry->sectionId)->limit(100);
        $generate->saveElementQuery($query);
        $record = ElementQueryRecord::find()->orderBy(['id' => SORT_DESC])->one();
        expect(ElementQuerySiteRecord::find()->select(['siteId'])->where(['queryId' => $record->id])->column())->toBe($expectedSiteIds);
        $generate->save('Site-aware query', createSiteUri($this->entry->siteId, 'site-aware/multi-' . $record->id));
        $data = new RefreshDataModel();
        $data->addElementId(Entry::class, $this->entry->id, [$this->entry->siteId]);
        expect(RefreshCacheHelper::getElementQueryCacheIds($record, $data))->toHaveCount(1);
    }
});

test('SiteAware global status changes and deletion invalidate dependencies across sites', function() {
    $own = siteAwareCache($this->entry, 'site-aware/status-own');
    $other = siteAwareCache($this->other, 'site-aware/status-other');
    $this->entry->enabled = false;
    expect(Craft::$app->getElements()->saveElement($this->entry))->toBeTrue();
    $data = Blitz::$plugin->refreshCache->refreshData;
    expect($data->getElementSiteIds(Entry::class, $this->entry->id))->toBeNull()
        ->and(RefreshCacheHelper::getElementCacheIds(Entry::class, $data))->toContain($own, $other);
    Blitz::$plugin->refreshCache->reset();
    expect(Craft::$app->getElements()->deleteElement($this->entry))->toBeTrue();
    $data = Blitz::$plugin->refreshCache->refreshData;
    expect($data->getElementSiteIds(Entry::class, $this->entry->id))->toBeNull()
        ->and(RefreshCacheHelper::getElementCacheIds(Entry::class, $data))->toContain($own, $other);
});

test('SiteAware diagnostics queries remain valid with site columns on dependencies', function() {
    $cacheId = siteAwareCache($this->entry, 'site-aware/diagnostics');
    expect(DiagnosticsHelper::getElementsCount($this->entry->siteId))->toBeGreaterThan(0)
        ->and(DiagnosticsHelper::getElementTypes($this->entry->siteId, $cacheId))->not->toBeEmpty()
        ->and(DiagnosticsHelper::getElementsQuery($this->entry->siteId, Entry::class, $cacheId)->all())->not->toBeEmpty();
});
