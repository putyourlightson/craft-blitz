<?php

use craft\elements\db\ElementQuery;
use craft\elements\Entry;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\events\RefreshCacheEvent;
use putyourlightson\blitz\jobs\RefreshCacheJob;
use putyourlightson\blitz\models\RefreshDataModel;
use putyourlightson\blitz\models\SettingsModel;
use putyourlightson\blitz\models\SiteUriModel;
use putyourlightson\blitz\records\CacheRecord;
use putyourlightson\blitz\services\ClearCacheService;
use putyourlightson\blitz\services\RefreshCacheService;
use yii\base\Event;

test('Refresh jobs prioritise element pages and clear direct and tagged pages before query checks', function(bool $cached, int $refreshMode) {
    $plugin = Blitz::$plugin;
    $plugin->cacheStorage->deleteAll();
    $plugin->flushCache->flushAll(true);
    $previousRefreshMode = $plugin->settings->refreshMode;
    $plugin->settings->refreshMode = $refreshMode;
    $entry = createEntry();
    $refreshData = new RefreshDataModel();
    $refreshData->addElementId(Entry::class, $entry->id, [$entry->siteId]);
    $refreshData->addSourceId(Entry::class, $entry->sectionId);

    $savePage = function(string $uri, bool $direct = false, bool $query = false) use ($plugin, $entry): int {
        $plugin->generateCache->reset();
        $plugin->generateCache->options->cachingEnabled = true;
        if ($direct) {
            $plugin->generateCache->addElement($entry);
        }
        if ($query) {
            $plugin->generateCache->addElementQuery(Entry::find()->sectionId($entry->sectionId)->siteId($entry->siteId));
        }
        $siteUri = createSiteUri($entry->siteId, $uri);
        $plugin->generateCache->save(createOutput(), $siteUri);

        return (int)CacheRecord::find()->select('id')->where($siteUri->toArray())->scalar();
    };

    // Reverse insertion order and overlap dependencies to verify sorting and deduplication.
    $savePage('z-direct', direct: true, query: true);
    $directCacheId = $savePage('m-direct', direct: true);
    $refreshData->addCacheId($savePage('n-explicit'));
    $savePage('b-query', query: true);
    $savePage('a-query', query: true);
    $taggedCacheId = $savePage('c-tagged');
    $plugin->cacheTags->saveTags('sectionId:' . $entry->sectionId, $taggedCacheId);
    $plugin->cacheTags->saveTags('sectionId:' . $entry->sectionId, $directCacheId);
    $savePage('unrelated');
    if ($cached) {
        $savePage($entry->uri, query: true);
    }

    $clearedUris = [];
    $refreshedUris = [];
    $queriesChecked = false;
    $queryHandler = function() use (&$queriesChecked) {
        $queriesChecked = true;
    };
    $afterClearHandler = function() use ($plugin, $entry, $cached, &$queriesChecked) {
        expect($queriesChecked)->toBeFalse();
        foreach (['m-direct', 'n-explicit', 'z-direct', 'c-tagged'] as $uri) {
            expect($plugin->cacheStorage->get(createSiteUri($entry->siteId, $uri)))->toBeNull();
        }
        foreach (['a-query', 'b-query', 'unrelated'] as $uri) {
            expect($plugin->cacheStorage->get(createSiteUri($entry->siteId, $uri)))->not->toBeNull();
        }
        expect($plugin->cacheStorage->get(createSiteUri($entry->siteId, $entry->uri)) !== null)->toBe($cached);
    };
    $clearHandler = function(RefreshCacheEvent $event) use (&$clearedUris) {
        $clearedUris = array_map(fn($siteUri) => $siteUri->uri, $event->siteUris);
    };
    $refreshHandler = function(RefreshCacheEvent $event) use (&$refreshedUris) {
        $refreshedUris = array_values(array_map(fn($siteUri) => $siteUri->uri, $event->siteUris));
        $event->isValid = false;
    };
    Event::on(ElementQuery::class, ElementQuery::EVENT_BEFORE_PREPARE, $queryHandler);
    $plugin->clearCache->on(ClearCacheService::EVENT_BEFORE_CLEAR_CACHE, $clearHandler);
    $plugin->clearCache->on(ClearCacheService::EVENT_AFTER_CLEAR_CACHE, $afterClearHandler);
    $plugin->refreshCache->on(RefreshCacheService::EVENT_BEFORE_REFRESH_CACHE, $refreshHandler);

    try {
        (new RefreshCacheJob(['data' => $refreshData->data]))->execute($plugin->queue);

        expect($refreshedUris[0])->toBe($entry->uri)
            ->and(array_slice($refreshedUris, 1))
            ->toEqualCanonicalizing(['a-query', 'b-query', 'c-tagged', 'm-direct', 'n-explicit', 'z-direct']);

        $expectedClearedUris = [];
        if ($refreshMode === SettingsModel::REFRESH_MODE_CLEAR_AND_GENERATE) {
            $expectedClearedUris = ['c-tagged', 'm-direct', 'n-explicit', 'z-direct'];
        }
        expect($clearedUris)->toBe($expectedClearedUris)
            ->and($queriesChecked)->toBeTrue();
    } finally {
        Event::off(ElementQuery::class, ElementQuery::EVENT_BEFORE_PREPARE, $queryHandler);
        $plugin->clearCache->off(ClearCacheService::EVENT_AFTER_CLEAR_CACHE, $afterClearHandler);
        $plugin->clearCache->off(ClearCacheService::EVENT_BEFORE_CLEAR_CACHE, $clearHandler);
        $plugin->refreshCache->off(RefreshCacheService::EVENT_BEFORE_REFRESH_CACHE, $refreshHandler);
        $plugin->settings->refreshMode = $previousRefreshMode;
    }
})->with([true, false])->with([
    SettingsModel::REFRESH_MODE_CLEAR_AND_GENERATE,
    SettingsModel::REFRESH_MODE_EXPIRE_AND_GENERATE,
]);

test('Changed elements’ own pages are sorted by site ID and URI', function() {
    $plugin = Blitz::$plugin;
    $plugin->cacheStorage->deleteAll();
    $plugin->flushCache->flushAll(true);
    $suffix = bin2hex(random_bytes(4));
    $lastEntry = createEntry(params: ['slug' => 'z-' . $suffix]);
    $firstEntry = createEntry(params: ['slug' => 'a-' . $suffix]);
    $siteIds = Craft::$app->getSites()->getAllSiteIds();
    sort($siteIds);
    expect($siteIds)->toHaveCount(2);

    // Put the later site first and create entries in reverse URI order.
    $refreshData = new RefreshDataModel();
    $refreshData->addElementId(Entry::class, $lastEntry->id, [$siteIds[1]]);
    $refreshData->addElementId(Entry::class, $firstEntry->id, [$siteIds[0]]);
    $refreshData->addElementId(Entry::class, $lastEntry->id, [$siteIds[0]]);
    $refreshData->addElementId(Entry::class, $firstEntry->id, [$siteIds[1]]);

    $refreshedPages = [];
    $handler = function(RefreshCacheEvent $event) use (&$refreshedPages) {
        $refreshedPages = array_map(fn(SiteUriModel $siteUri) => [(int)$siteUri->siteId, $siteUri->uri], $event->siteUris);
        $event->isValid = false;
    };
    $plugin->refreshCache->on(RefreshCacheService::EVENT_BEFORE_REFRESH_CACHE, $handler);

    try {
        (new RefreshCacheJob(['data' => $refreshData->data]))->execute($plugin->queue);

        expect($refreshedPages)->toBe([
            [$siteIds[0], $firstEntry->uri],
            [$siteIds[0], $lastEntry->uri],
            [$siteIds[1], $firstEntry->uri],
            [$siteIds[1], $lastEntry->uri],
        ]);
    } finally {
        $plugin->refreshCache->off(RefreshCacheService::EVENT_BEFORE_REFRESH_CACHE, $handler);
    }
});
