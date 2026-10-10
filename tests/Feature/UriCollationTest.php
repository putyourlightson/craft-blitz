<?php

/**
 * Tests distinct URI cache tracking and refresh dependencies after the collation migration.
 */

use craft\db\mysql\ColumnSchema;
use craft\elements\Entry;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\helpers\RefreshCacheHelper;
use putyourlightson\blitz\helpers\SiteUriHelper;
use putyourlightson\blitz\migrations\m261010_120000_make_uri_comparisons_sensitive;
use putyourlightson\blitz\records\CacheRecord;
use putyourlightson\blitz\records\ElementCacheRecord;

beforeEach(function() {
    Blitz::$plugin->settings->cachingEnabled = true;
    Blitz::$plugin->generateCache->reset();
    Blitz::$plugin->cacheStorage->deleteAll();
    Blitz::$plugin->flushCache->flushAll(true);
});

test('URI migration preserves the existing MySQL charset, length and text', function(string $charset) {
    $db = Craft::$app->getDb();
    if (!$db->getIsMysql()) {
        $this->markTestSkipped('MySQL charset preservation test.');
    }

    /** @var ColumnSchema $originalColumn */
    $originalColumn = $db->getTableSchema(CacheRecord::tableName(), true)->columns['uri'];
    $migration = new m261010_120000_make_uri_comparisons_sensitive(['compact' => true]);

    try {
        $migration->alterColumn(CacheRecord::tableName(), 'uri', 'varchar(1024) COLLATE ' . $charset . '_general_ci NOT NULL');
        $siteUri = createSiteUri(uri: 'tëst');
        $db->createCommand()->insert(CacheRecord::tableName(), $siteUri->toArray())->execute();

        $migration->safeUp();

        /** @var ColumnSchema $column */
        $column = $db->getTableSchema(CacheRecord::tableName(), true)->columns['uri'];
        expect($column->collation)->toBe($charset . '_bin')
            ->and($column->size)->toBe(1024)
            ->and($column->allowNull)->toBeFalse()
            ->and(CacheRecord::find()->select('uri')->scalar())->toBe('tëst')
            ->and(CacheRecord::find()->where(['uri' => 'test'])->count())->toEqual(0);
    } finally {
        $migration->alterColumn(CacheRecord::tableName(), 'uri', $originalColumn->dbType . ' COLLATE ' . $originalColumn->collation . ' NOT NULL');
        $db->getSchema()->refresh();
    }
})->with(['utf8mb3', 'utf8mb4', 'latin1']);

test('Distinct URI variants retain their cache files and refresh dependencies', function(string $uri, string $variant) {
    $entry = createEntry();
    $siteUris = [createSiteUri(uri: $uri), createSiteUri(uri: $variant)];

    foreach ($siteUris as $siteUri) {
        Blitz::$plugin->generateCache->addElement($entry);
        Blitz::$plugin->generateCache->save('Original content', $siteUri);
    }

    expect(CacheRecord::class)->toHaveRecordCount(2)
        ->and(ElementCacheRecord::class)->toHaveRecordCount(2, ['elementId' => $entry->id]);

    $cacheIds = [];
    foreach ($siteUris as $siteUri) {
        $ids = SiteUriHelper::getCacheIdsFromSiteUris([$siteUri]);
        expect($ids)->toHaveCount(1)
            ->and(Blitz::$plugin->cacheStorage->get($siteUri))->toContain('Original content');
        $cacheIds[] = $ids[0];
    }
    expect($cacheIds[0])->not->toEqual($cacheIds[1]);

    $entry->title = 'Changed title';
    Blitz::$plugin->refreshCache->addElement($entry);
    expect(RefreshCacheHelper::getElementCacheIds(Entry::class, Blitz::$plugin->refreshCache->refreshData))
        ->toEqualCanonicalizing($cacheIds);

    // Regenerating either variant must retain the other variant's dependencies.
    foreach ($siteUris as $siteUri) {
        Blitz::$plugin->generateCache->addElement($entry);
        Blitz::$plugin->generateCache->save('Updated content', $siteUri);
    }
    foreach ($siteUris as $siteUri) {
        expect(Blitz::$plugin->cacheStorage->get($siteUri))->toContain('Updated content');
    }
    expect(CacheRecord::class)->toHaveRecordCount(2);
    $updatedCacheIds = SiteUriHelper::getCacheIdsFromSiteUris($siteUris);
    expect(RefreshCacheHelper::getElementCacheIds(Entry::class, Blitz::$plugin->refreshCache->refreshData))
        ->toEqualCanonicalizing($updatedCacheIds);
})->with([
    'accents' => ['test', 'tëst'],
    'case' => ['test', 'Test'],
    'query string case' => ['test?value=a', 'test?value=A'],
]);
