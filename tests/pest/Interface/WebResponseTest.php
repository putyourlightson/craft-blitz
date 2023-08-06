<?php

/**
 * Tests that cached web responses contain the correct headers and comments.
 */

use putyourlightson\blitz\Blitz;

// Get the cached response rather than using the `get` function, which doesn’t work.

test('Cached response adds “powered by” header once', function () {
    Craft::$app->config->general->sendPoweredByHeader = true;
    $response = Blitz::$plugin->cacheRequest->getCachedResponse(createSiteUri(uri: 'blitz'));
    $value = $response->headers->get('X-Powered-By');

    expect($response->headers->get('X-Powered-By'))
        ->toContainOnce('Blitz', 'Craft CMS');
});

test('Cached response overwrites “powered by” header', function () {
    Craft::$app->config->general->sendPoweredByHeader = false;
    $response = Blitz::$plugin->cacheRequest->getCachedResponse(createSiteUri(uri: 'blitz'));

    expect($response->headers->get('X-Powered-By'))
        ->toBe('Blitz');
});

test('Cached response contains comments', function () {
    $response = Blitz::$plugin->cacheRequest->getCachedResponse(createSiteUri(uri: 'blitz'));

    expect($response->content)
        ->toContain('Cached by Blitz', 'Served by Blitz');
});
