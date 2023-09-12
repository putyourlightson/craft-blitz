<?php

/**
 * Tests the storing of cached values using the cache storage drivers.
 */

use craft\helpers\StringHelper;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\drivers\storage\FileStorage;

afterEach(function() {
    Blitz::$plugin->cacheStorage->deleteAll();
});

test('255 character site URI can be saved', function(string $driver) {
    $output = createOutput();
    $siteUri = createSiteUri(uri: StringHelper::randomString(255));
    Blitz::$plugin->set('cacheStorage', $driver);
    Blitz::$plugin->cacheStorage->save($output, $siteUri);

    expect(Blitz::$plugin->cacheStorage->get($siteUri))
        ->toBe($output);
})->with('cacheStorageDrivers');

test('Long site URI can be saved except for by file storage driver', function(string $driver) {
    $output = createOutput();
    $siteUri = createSiteUri(uri: StringHelper::randomString(1000));
    Blitz::$plugin->set('cacheStorage', $driver);
    Blitz::$plugin->cacheStorage->save($output, $siteUri);
    $expectedValue = $driver === FileStorage::class ? '' : $output;

    expect(Blitz::$plugin->cacheStorage->get($siteUri))
        ->toBe($expectedValue);
})->with('cacheStorageDrivers');

test('Site URI is decoded before being saved', function(string $driver) {
    $output = createOutput();
    $siteUri = createSiteUri(uri: 'möbelträgerfüße');
    Blitz::$plugin->set('cacheStorage', $driver);
    Blitz::$plugin->cacheStorage->save($output, $siteUri);
    $siteUri->uri = rawurldecode($siteUri->uri);

    expect(Blitz::$plugin->cacheStorage->get($siteUri))
        ->toBe($output);
})->with('cacheStorageDrivers');

test('Compressed cached value can be fetched compressed and uncompressed', function(string $driver) {
    $output = createOutput();
    $siteUri = createSiteUri();
    Blitz::$plugin->set('cacheStorage', $driver);
    Blitz::$plugin->cacheStorage->compressCachedValues = true;
    Blitz::$plugin->cacheStorage->save($output, $siteUri);

    expect(gzdecode(Blitz::$plugin->cacheStorage->getCompressed($siteUri)))
        ->toBe($output)
        ->and(Blitz::$plugin->cacheStorage->get($siteUri))
        ->toBe($output);
})->with('cacheStorageDrivers');

test('Cached value of site URI is deleted', function(string $driver) {
    $siteUri = createSiteUri();
    Blitz::$plugin->set('cacheStorage', $driver);
    Blitz::$plugin->cacheStorage->save(createOutput(), $siteUri);
    Blitz::$plugin->cacheStorage->deleteUris([$siteUri]);

    expect(Blitz::$plugin->cacheStorage->get($siteUri))
        ->toBeEmpty();
})->with('cacheStorageDrivers');

test('Compressed cached value of site URI is deleted', function(string $driver) {
    $siteUri = createSiteUri();
    Blitz::$plugin->set('cacheStorage', $driver);
    Blitz::$plugin->cacheStorage->compressCachedValues = true;
    Blitz::$plugin->cacheStorage->save(createOutput(), $siteUri);
    Blitz::$plugin->cacheStorage->deleteUris([$siteUri]);

    expect(Blitz::$plugin->cacheStorage->getCompressed($siteUri))
        ->toBeEmpty();
})->with('cacheStorageDrivers');

test('Cached value of decoded site URI is deleted', function(string $driver) {
    $siteUri = createSiteUri(uri: 'möbelträgerfüße');
    Blitz::$plugin->set('cacheStorage', $driver);
    Blitz::$plugin->cacheStorage->save(createOutput(), $siteUri);
    $siteUri->uri = rawurldecode($siteUri->uri);
    Blitz::$plugin->cacheStorage->deleteUris([$siteUri]);

    expect(Blitz::$plugin->cacheStorage->get($siteUri))
        ->toBeEmpty();
})->with('cacheStorageDrivers');

test('All cached values are deleted', function(string $driver) {
    $siteUri = createSiteUri();
    Blitz::$plugin->set('cacheStorage', $driver);
    Blitz::$plugin->cacheStorage->save(createOutput(), $siteUri);
    Blitz::$plugin->cacheStorage->deleteAll();

    expect(Blitz::$plugin->cacheStorage->get($siteUri))
        ->toBeEmpty();
})->with('cacheStorageDrivers');
