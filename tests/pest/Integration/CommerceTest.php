<?php

/**
 * Tests that Commerce variants are refreshed on order completion so that their stock is updated.
 */

use craft\commerce\elements\Order;
use Mockery\MockInterface;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\drivers\integrations\CommerceIntegration;
use putyourlightson\blitz\services\RefreshCacheService;

beforeEach(function() {
    Blitz::$plugin->set('refreshCache', Mockery::mock(RefreshCacheService::class . '[refresh,refreshAll]'));
    Blitz::$plugin->refreshCache->reset();
    Blitz::$plugin->refreshCache->batchMode = false;
})->skip(fn() => !integrationIsActive(CommerceIntegration::class), 'Commerce integration not found in active integrations.');

test('Variants are refreshed on order completion', function() {
    /** @var MockInterface $refreshCache */
    $refreshCache = Blitz::$plugin->refreshCache;
    $refreshCache->shouldReceive('refresh')->once();
    $refreshCache->shouldNotReceive('refreshAll');

    [$variant, $order] = createProductVariantOrder(batchMode: true);
    $order->trigger(Order::EVENT_AFTER_COMPLETE_ORDER);

    expect(Blitz::$plugin->refreshCache->refreshData->getElementIds($variant::class))
        ->toBe([$variant->id]);
});
