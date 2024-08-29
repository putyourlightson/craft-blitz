<?php

/**
 * Tests the default plugin settings.
 */

use putyourlightson\blitz\Blitz;

test('The default cache control header doesn’t allow caching', function() {
    expect(Blitz::$plugin->settings->defaultCacheControlHeader)
        ->toContain('no-store');
});

test('The cache control header doesn’t allow browser caching', function() {
    expect(Blitz::$plugin->settings->cacheControlHeader)
        ->toContain('max-age=0');
});

test('The expired cache control header doesn’t allow browser caching', function() {
    expect(Blitz::$plugin->settings->cacheControlHeaderExpired)
        ->toContain('max-age=0');
});
