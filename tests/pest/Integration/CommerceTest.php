<?php

/**
 * Tests that Commerce variants are refreshed on order completion so that their stock is updated.
 */

use craft\commerce\elements\Order;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\drivers\integrations\CommerceIntegration;

beforeEach(function() {
    Blitz::$plugin->refreshCache->reset();
})->skip(fn() => !integrationIsActive(CommerceIntegration::class), 'Commerce integration not found in active integrations.');

test('Variant with unlimited stock is refreshed on order completion', function() {
    [$variant, $order] = createProductVariantOrder(hasUnlimitedStock: false);
    $order->trigger(Order::EVENT_AFTER_COMPLETE_ORDER);

    expect(Blitz::$plugin->refreshCache->refreshData->getElementIds($variant::class))
        ->toBe([$variant->id]);
});

test('Variant without unlimited stock is not refreshed on order completion', function() {
    [$variant, $order] = createProductVariantOrder();
    $order->trigger(Order::EVENT_AFTER_COMPLETE_ORDER);

    expect(Blitz::$plugin->refreshCache->refreshData->getElementIds($variant::class))
        ->toBe([]);
});
