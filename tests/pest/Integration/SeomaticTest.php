<?php

/**
 * Tests that cached pages are refreshed when SEOmatic meta containers are invalidated.
 */

use craft\helpers\App;
use Mockery\MockInterface;
use nystudio107\seomatic\seoelements\SeoEntry;
use nystudio107\seomatic\Seomatic;
use nystudio107\seomatic\services\MetaBundles;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\drivers\integrations\SeomaticIntegration;
use putyourlightson\blitz\services\RefreshCacheService;

// TODO: move skips from tests to the beforeEach function

beforeEach(function() {
    Blitz::$plugin->set('refreshCache', Mockery::mock(RefreshCacheService::class . '[refresh,refreshAll]'));
    Blitz::$plugin->refreshCache->reset();
    Blitz::$plugin->refreshCache->batchMode = false;

    if (integrationIsActive(SeomaticIntegration::class)) {
        // Prevent invalidation of meta bundles and therefore queue jobs.
        $metaBundles = Mockery::mock(MetaBundles::class . '[invalidateMetaBundleByElement]');
        $metaBundles->shouldReceive('invalidateMetaBundleByElement');
        Seomatic::$plugin->set('metaBundles', $metaBundles);
        Seomatic::$plugin->metaBundles->deleteMetaBundleBySourceId(SeoEntry::getMetaBundleType(), App::env('TEST_SECTION_ID'), App::env('TEST_SITE_ID'));
    }
});

test('Invalidate container caches event without a URL or source triggers a refresh all', function() {
    /** @var MockInterface $refreshCache */
    $refreshCache = Blitz::$plugin->refreshCache;
    $refreshCache->shouldNotReceive('refresh');
    $refreshCache->shouldReceive('refreshAll')->once();

    createEntry(batchMode: true);
    Seomatic::$plugin->metaContainers->invalidateCaches();
})->skip(fn() => !integrationIsActive(SeomaticIntegration::class), 'SEOmatic integration not found in active integrations.');

test('Invalidate container caches event with a specific source triggers a refresh', function() {
    /** @var MockInterface $refreshCache */
    $refreshCache = Blitz::$plugin->refreshCache;
    $refreshCache->shouldReceive('refresh')->once();
    $refreshCache->shouldNotReceive('refreshAll');

    $entry = createEntry(batchMode: true);
    Seomatic::$plugin->metaContainers->invalidateContainerCacheById(App::env('TEST_SECTION_ID'), SeoEntry::getMetaBundleType(), App::env('TEST_SITE_ID'));

    expect(Blitz::$plugin->refreshCache->refreshData->getElementIds($entry::class))
        ->toContain($entry->id);
})->skip(fn() => !integrationIsActive(SeomaticIntegration::class), 'SEOmatic integration not found in active integrations.');

test('Invalidate container caches event for a specific element does not trigger a refresh', function() {
    /** @var MockInterface $refreshCache */
    $refreshCache = Blitz::$plugin->refreshCache;
    $refreshCache->shouldNotReceive('refresh');
    $refreshCache->shouldNotReceive('refreshAll');

    $entry = createEntry(batchMode: true);
    Seomatic::$plugin->metaContainers->invalidateContainerCacheByPath($entry->uri);
})->skip(fn() => !integrationIsActive(SeomaticIntegration::class), 'SEOmatic integration not found in active integrations.');
