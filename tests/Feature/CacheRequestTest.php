<?php

/**
 * Tests whether requests are cacheable and under what circumstances.
 */

use craft\helpers\StringHelper;
use craft\web\TemplateResponseFormatter;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\models\SettingsModel;
use putyourlightson\blitz\services\CacheRequestService;

beforeEach(function() {
    Blitz::$plugin->settings->includedUriPatterns = [
        [
            'siteId' => '',
            'uriPattern' => '',
        ],
        [
            'siteId' => '',
            'uriPattern' => 'page|Page|Übergröße',
        ],
    ];
    Blitz::$plugin->settings->excludedUriPatterns = [];
    Blitz::$plugin->settings->includedQueryStringParams = [
        [
            'siteId' => '',
            'queryStringParam' => '.*',
        ],
    ];
    Blitz::$plugin->settings->excludedQueryStringParams = [
        [
            'siteId' => '',
            'queryStringParam' => 'gclid',
        ],
    ];
    Blitz::$plugin->settings->queryStringCaching = SettingsModel::QUERY_STRINGS_CACHE_URLS_AS_UNIQUE_PAGES;
    Blitz::$plugin->settings->outputComments = false;
    Blitz::$plugin->generateCache->options->outputComments = null;
    Blitz::$plugin->cacheStorage->deleteAll();
});

test('Request matching included URI pattern is cacheable', function() {
    sendRequest();

    expect(Blitz::$plugin->cacheRequest->getIsCacheableRequest())
        ->toBeTrue();
});

test('Request with generate token is cacheable', function() {
    $token = Craft::$app->getTokens()->createToken('blitz/generator/generate');
    sendRequest('page?token=' . $token);

    expect(Blitz::$plugin->cacheRequest->getIsCacheableRequest())
        ->toBeTrue();
});

test('Request with `no-cache` param is not cacheable', function() {
    sendRequest('page?no-cache=1');

    expect(Blitz::$plugin->cacheRequest->getIsCacheableRequest())
        ->toBeFalse();
});

test('Request with token is not cacheable', function() {
    $token = Craft::$app->getTokens()->createToken('blitz/test');
    sendRequest('page?token=' . $token);

    expect(Blitz::$plugin->cacheRequest->getIsCacheableRequest())
        ->toBeFalse();
});

test('Request with a cached include path is a cached include', function() {
    $uri = CacheRequestService::CACHED_INCLUDE_PREFIX . '?index=1234567890';

    expect(Blitz::$plugin->cacheRequest->getIsCachedInclude($uri))
        ->toBeTrue();
});

test('Request with a dynamic include path is a dynamic include', function() {
    $uri = CacheRequestService::DYNAMIC_INCLUDE_PREFIX . '?index=1234567890';

    expect(Blitz::$plugin->cacheRequest->getIsDynamicInclude($uri))
        ->toBeTrue();
});

test('Requested cacheable site URI includes allowed query strings when urls cached as unique pages', function() {
    sendRequest('page?p=page&x=1&y=2&gclid=123');
    $siteUri = Blitz::$plugin->cacheRequest->getRequestedCacheableSiteUri();

    expect($siteUri->uri)
        ->toBe('page?x=1&y=2');
});

test('Requested cacheable site URI does not include query strings when urls cached as same page', function() {
    sendRequest('page?p=page&x=1&y=2&gclid=123');
    Blitz::$plugin->settings->queryStringCaching = SettingsModel::QUERY_STRINGS_CACHE_URLS_AS_SAME_PAGE;
    $siteUri = Blitz::$plugin->cacheRequest->getRequestedCacheableSiteUri();

    expect($siteUri->uri)
        ->toBe('page');
});

// TODO: figure out why a `Page Not Found` error is thrown.
test('Requested cacheable site URI includes page trigger', function() {
    Craft::$app->config->general->pageTrigger = 'p';
    sendRequest('page/p1');
    $siteUri = Blitz::$plugin->cacheRequest->getRequestedCacheableSiteUri();

    expect($siteUri->uri)
        ->toBe('page/p1');
})->todo();

test('Requested cacheable site URI works with regular expressions', function() {
    Blitz::$plugin->settings->excludedQueryStringParams = [
        [
            'siteId' => '',
            'queryStringParam' => '^(?!sort$|search$).*',
        ],
    ];
    sendRequest('page?sort=asc&search=waldo&spidy=123');
    $siteUri = Blitz::$plugin->cacheRequest->getRequestedCacheableSiteUri();

    expect($siteUri->uri)
        ->toBe('page?sort=asc&search=waldo');
});

test('Site URI with uppercase URI is cacheable', function(string $uri) {
    $siteUri = createSiteUri(uri: $uri);
    Blitz::$plugin->settings->onlyCacheLowercaseUris = false;

    expect(Blitz::$plugin->cacheRequest->getIsCacheableSiteUri($siteUri))
        ->toBeTrue();
})->with([
    'Page',
    'Übergröße',
]);

test('Site URI with uppercase URI is not cacheable when disallowed', function(string $uri) {
    $siteUri = createSiteUri(uri: $uri);
    Blitz::$plugin->settings->onlyCacheLowercaseUris = true;

    expect(Blitz::$plugin->cacheRequest->getIsCacheableSiteUri($siteUri))
        ->toBeFalse();
})->with([
    'Page',
    'Übergröße',
]);

test('Site URI with included URI pattern is cacheable xyzzz', function() {
    $siteUri = createSiteUri();

    expect(Blitz::$plugin->cacheRequest->getIsCacheableSiteUri($siteUri))
        ->toBeTrue();
});

test('Site URI with disabled included URI pattern is not cacheable', function() {
    $siteUri = createSiteUri(uri: 'cacheable');
    Blitz::$plugin->settings->includedUriPatterns[] = [
        'enabled' => false,
        'siteId' => '',
        'uriPattern' => 'cacheable',
    ];

    expect(Blitz::$plugin->cacheRequest->getIsCacheableSiteUri($siteUri))
        ->toBeFalse();
});

test('Site URI with excluded URI pattern is not cacheable', function() {
    $siteUri = createSiteUri(uri: 'page-to-exclude');
    Blitz::$plugin->settings->excludedUriPatterns[] = [
        'siteId' => '',
        'uriPattern' => 'exclude',
    ];

    expect(Blitz::$plugin->cacheRequest->getIsCacheableSiteUri($siteUri))
        ->toBeFalse();
});

test('Site URI with disabled excluded URI pattern is cacheable', function() {
    $siteUri = createSiteUri(uri: 'cacheable');
    Blitz::$plugin->settings->includedUriPatterns[] = [
        'siteId' => '',
        'uriPattern' => 'cacheable',
    ];
    Blitz::$plugin->settings->excludedUriPatterns[] = [
        'enabled' => false,
        'siteId' => '',
        'uriPattern' => 'cacheable',
    ];

    expect(Blitz::$plugin->cacheRequest->getIsCacheableSiteUri($siteUri))
        ->toBeTrue();
});

test('Site URI with `admin` in URI is cacheable', function() {
    $siteUri = createSiteUri(uri: 'admin-page');

    expect(Blitz::$plugin->cacheRequest->getIsCacheableSiteUri($siteUri))
        ->toBeTrue();
});

test('Site URI with `index.php` in URI is not cacheable', function() {
    $siteUri = createSiteUri(uri: 'index.php');

    expect(Blitz::$plugin->cacheRequest->getIsCacheableSiteUri($siteUri))
        ->toBeFalse();
});

test('Site URI with `index.php` and allowed action in URI is cacheable', function() {
    Blitz::$plugin->settings->includedUriPatterns = [
        [
            'siteId' => '',
            'uriPattern' => 'actions',
        ],
    ];
    $siteUri = createSiteUri(uri: 'index.php?p=actions/test');

    expect(Blitz::$plugin->cacheRequest->getIsCacheableSiteUri($siteUri))
        ->toBeTrue();
});

test('Site URI with max URI length is cacheable', function() {
    $siteUri = createSiteUri(uri: 'page' . StringHelper::randomString(Blitz::$plugin->settings->maxUriLength - 4));

    expect(Blitz::$plugin->cacheRequest->getIsCacheableSiteUri($siteUri))
        ->toBeTrue();
});

test('Site URI with max URI length exceeded is not cacheable', function() {
    $siteUri = createSiteUri(uri: 'page' . StringHelper::randomString(Blitz::$plugin->settings->maxUriLength));

    expect(Blitz::$plugin->cacheRequest->getIsCacheableSiteUri($siteUri))
        ->toBeFalse();
});

test('Site URI with period-only segments is not cacheable', function(string $uri) {
    $siteUri = createSiteUri(uri: $uri);

    expect(Blitz::$plugin->cacheRequest->getIsCacheableSiteUri($siteUri))
        ->toBeFalse();
})->with([
    './page',
    'page/.',
    'page/./1',
    '%2E/page',
    'page/%2E',
    'page/%2E/1',
]);

test('URI patterns with matching regular expressions are matched', function() {
    $matchesUriPatterns = Blitz::$plugin->cacheRequest->matchesUriPatterns(
        createSiteUri(),
        [['siteId' => 1, 'uriPattern' => '.*']]
    );
    expect($matchesUriPatterns)
        ->toBeTrue();

    $matchesUriPatterns = Blitz::$plugin->cacheRequest->matchesUriPatterns(
        createSiteUri(),
        [['siteId' => 1, 'uriPattern' => '(\/?)']]
    );
    expect($matchesUriPatterns)
        ->toBeTrue();

    $matchesUriPatterns = Blitz::$plugin->cacheRequest->matchesUriPatterns(
        createSiteUri(),
        [['siteId' => 1, 'uriPattern' => '^page$']]
    );
    expect($matchesUriPatterns)
        ->toBeTrue();
});

test('URI patterns without matching regular expressions are not matched', function() {
    $matchesUriPatterns = Blitz::$plugin->cacheRequest->matchesUriPatterns(
        createSiteUri(),
        [['siteId' => 1, 'uriPattern' => '^my-page$']]
    );
    expect($matchesUriPatterns)
        ->toBeFalse();
});

test('Response with a template response format is cacheable', function() {
    $response = Craft::$app->getResponse();
    $response->format = TemplateResponseFormatter::FORMAT;
    Blitz::$plugin->settings->cacheNonHtmlResponses = false;

    expect(Blitz::$plugin->cacheRequest->getIsCacheableResponse($response))
        ->toBeTrue();
});
