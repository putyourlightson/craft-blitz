<?php

/**
 * Tests that cached pages are refreshed when SEOmatic meta containers are invalidated.
 */

use Mockery\MockInterface;
use nystudio107\seomatic\seoelements\SeoEntry;
use nystudio107\seomatic\Seomatic;
use nystudio107\seomatic\services\MetaBundles;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\drivers\integrations\SeomaticIntegration;
use putyourlightson\blitz\services\RefreshCacheService;

beforeEach(function() {
    Blitz::$plugin->set('refreshCache', Mockery::mock(RefreshCacheService::class . '[refreshAll]'));
    Blitz::$plugin->refreshCache->reset();

    if (integrationIsActive(SeomaticIntegration::class)) {
        // Prevent invalidation of meta bundles and therefore queue jobs.
        $metaBundles = Mockery::mock(MetaBundles::class . '[invalidateMetaBundleByElement]');
        $metaBundles->shouldReceive('invalidateMetaBundleByElement');
        Seomatic::$plugin->set('metaBundles', $metaBundles);
        Seomatic::$plugin->metaBundles->deleteMetaBundleBySourceId(SeoEntry::getMetaBundleType(), getChannelSectionId(), getSiteId());
    }
})->skip(fn() => !integrationIsActive(SeomaticIntegration::class), 'SEOmatic integration not found in active integrations.');

test('Invalidate container caches event without a URL or source triggers a refresh all', function() {
    /** @var MockInterface $refreshCache */
    $refreshCache = Blitz::$plugin->refreshCache;
    $refreshCache->shouldReceive('refreshAll')->once();

    createEntry();
    Seomatic::$plugin->metaContainers->invalidateCaches();
});

test('Invalidate container caches event with a specific source triggers a refresh', function() {
    /** @var MockInterface $refreshCache */
    $refreshCache = Blitz::$plugin->refreshCache;
    $refreshCache->shouldNotReceive('refreshAll');

    $entry = createEntry();
    Seomatic::$plugin->metaContainers->invalidateContainerCacheById($entry->sectionId, SeoEntry::getMetaBundleType(), $entry->siteId);

    expect(Blitz::$plugin->refreshCache->refreshData->getElementIds($entry::class))
        ->toContain($entry->id);
});

test('Invalidate container caches event for a specific element does not trigger a refresh', function() {
    /** @var MockInterface $refreshCache */
    $refreshCache = Blitz::$plugin->refreshCache;
    $refreshCache->shouldNotReceive('refreshAll');

    $entry = createEntry();
    Seomatic::$plugin->metaContainers->invalidateContainerCacheByPath($entry->uri);
});
