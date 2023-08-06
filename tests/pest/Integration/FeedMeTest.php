<?php

/**
 * Tests that Feed Me imports refresh the cache with batch mode enabled.
 */

use craft\feedme\Plugin;
use craft\feedme\services\Process;
use Mockery\MockInterface;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\drivers\integrations\FeedMeIntegration;
use putyourlightson\blitz\services\RefreshCacheService;

// TODO: move skips from tests to the beforeEach function

beforeEach(function () {
    Blitz::$plugin->set('refreshCache', Mockery::mock(RefreshCacheService::class . '[refresh]'));
    Blitz::$plugin->refreshCache->reset();
    Blitz::$plugin->refreshCache->batchMode = false;
});

test('Cache is refreshed with batch mode enabled', function () {
    /** @var MockInterface $refreshCache */
    $refreshCache = Blitz::$plugin->refreshCache;
    $refreshCache->shouldReceive('refresh')->once();

    Plugin::$plugin->process->trigger(Process::EVENT_BEFORE_PROCESS_FEED);
    Plugin::$plugin->process->trigger(Process::EVENT_AFTER_PROCESS_FEED);

    expect(Blitz::$plugin->refreshCache->batchMode)
        ->toBeTrue();
})->skip(getIsIntegrationInactive(FeedMeIntegration::class), 'Feed Me integration not found in active integrations.');
