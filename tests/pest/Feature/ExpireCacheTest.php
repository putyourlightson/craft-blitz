<?php

/**
 * Tests marking cached values as expired when `REFRESH_MODE_EXPIRE` is selected.
 */

use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\models\SettingsModel;

beforeEach(function() {
    Blitz::$plugin->settings->refreshMode = SettingsModel::REFRESH_MODE_EXPIRE;
    Blitz::$plugin->cacheStorage->deleteAll();
    Blitz::$plugin->flushCache->flushAll();
});

test('Refreshing the entire cache marks the cache as expired', function() {
    Blitz::$plugin->generateCache->save(createOutput(), createSiteUri());
    Blitz::$plugin->refreshCache->refreshAll();

    expect(Blitz::$plugin->expireCache->getExpiredCacheIds())
        ->not->toBeEmpty();
});

test('Refreshing a site marks the cache as expired', function() {
    $siteUri = createSiteUri();
    Blitz::$plugin->generateCache->save(createOutput(), $siteUri);
    Blitz::$plugin->refreshCache->refreshSite($siteUri->siteId);

    expect(Blitz::$plugin->expireCache->getExpiredCacheIds())
        ->not->toBeEmpty();
});

test('Refreshing a site URI marks the cache as expired', function() {
    $siteUri = createSiteUri();
    Blitz::$plugin->generateCache->save(createOutput(), $siteUri);
    Blitz::$plugin->refreshCache->refreshSiteUris([$siteUri]);

    expect(Blitz::$plugin->expireCache->getExpiredCacheIds())
        ->not->toBeEmpty();
});
