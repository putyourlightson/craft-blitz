<?php

/**
 * Tests whether requests are cacheable and under what circumstances.
 */

use craft\helpers\StringHelper;
use craft\helpers\UrlHelper;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\models\SettingsModel;

beforeEach(function () {
    Blitz::$plugin->settings->includedUriPatterns = [
        [
            'siteId' => '',
            'uriPattern' => '.*',
        ],
    ];
    Blitz::$plugin->settings->queryStringCaching = SettingsModel::QUERY_STRINGS_CACHE_URLS_AS_UNIQUE_PAGES;
    Blitz::$plugin->settings->outputComments = false;
    Blitz::$plugin->generateCache->options->outputComments = null;
});

afterEach(function () {
    sendRequest();
});

test('Request matching included uri pattern is cacheable', function () {
    sendRequest();

    expect(Blitz::$plugin->cacheRequest->getIsCacheableRequest())
        ->toBeTrue();
});

test('Request with generate token is cacheable', function () {
    $token = Craft::$app->getTokens()->createToken('blitz/generator/generate');
    sendRequest('page?token=' . $token);

    expect(Blitz::$plugin->cacheRequest->getIsCacheableRequest())
        ->toBeTrue();
});

test('Request with `no-cache` param is not cacheable', function () {
    sendRequest('page?no-cache=1');

    expect(Blitz::$plugin->cacheRequest->getIsCacheableRequest())
        ->toBeFalse();
});

test('Request with token is not cacheable', function () {
    $token = Craft::$app->getTokens()->createToken('xyz');
    sendRequest('page?token=' . $token);

    expect(Blitz::$plugin->cacheRequest->getIsCacheableRequest())
        ->toBeFalse();
});

test('Request with `_includes` path is a cached include', function () {
    expect(Blitz::$plugin->cacheRequest->getIsCachedInclude('/_includes/xyz'))
        ->toBeTrue();
});

test('Request with include action is a cached include', function () {
    sendRequest(UrlHelper::siteUrl('', ['action' => 'blitz/include/cached']));

    expect(Blitz::$plugin->cacheRequest->getIsCachedInclude())
        ->toBeTrue();
});

test('Requested cacheable site URI includes allowed query strings when urls cached as unique pages', function () {
    sendRequest('page?p=page&x=1&y=2&gclid=123');
    $siteUri = Blitz::$plugin->cacheRequest->getRequestedCacheableSiteUri();

    expect($siteUri->uri)
        ->toBe('page?x=1&y=2');
});

test('Requested cacheable site URI does not include query strings when urls cached as same page', function () {
    sendRequest('page?p=page&x=1&y=2&gclid=123');
    Blitz::$plugin->settings->queryStringCaching = SettingsModel::QUERY_STRINGS_CACHE_URLS_AS_SAME_PAGE;
    $siteUri = Blitz::$plugin->cacheRequest->getRequestedCacheableSiteUri();

    expect($siteUri->uri)
        ->toBe('page');
});

test('Requested cacheable site URI includes page trigger', function () {
    sendRequest('page/p1');
    $siteUri = Blitz::$plugin->cacheRequest->getRequestedCacheableSiteUri();

    expect($siteUri->uri)
        ->toBe('page/p1');
});

test('Requested cacheable site URI works with regular expressions', function () {
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

test('Site URI with included uri pattern is cacheable', function () {
    $siteUri = createSiteUri();

    expect(Blitz::$plugin->cacheRequest->getIsCacheableSiteUri($siteUri))
        ->toBeTrue();
});

test('Site URI with excluded uri pattern is not cacheable', function () {
    $siteUri = createSiteUri(uri: 'page-to-exclude');
    Blitz::$plugin->settings->excludedUriPatterns = [
        [
            'siteId' => '',
            'uriPattern' => 'exclude',
        ],
    ];

    expect(Blitz::$plugin->cacheRequest->getIsCacheableSiteUri($siteUri))
        ->toBeFalse();
});

test('Site URI with `admin` in uri is cacheable', function () {
    $siteUri = createSiteUri(uri: 'admin-page');

    expect(Blitz::$plugin->cacheRequest->getIsCacheableSiteUri($siteUri))
        ->toBeTrue();
});

test('Site URI with `index.php` in uri is not cacheable', function () {
    $siteUri = createSiteUri(uri: 'index.php');

    expect(Blitz::$plugin->cacheRequest->getIsCacheableSiteUri($siteUri))
        ->toBeFalse();
});

test('Site URI with max uri length is cacheable', function () {
    $siteUri = createSiteUri(uri: StringHelper::randomString(Blitz::$plugin->settings->maxUriLength));

    expect(Blitz::$plugin->cacheRequest->getIsCacheableSiteUri($siteUri))
        ->toBeTrue();
});

test('Site URI with max uri length exceeded is not cacheable', function () {
    $siteUri = createSiteUri(uri: StringHelper::randomString(Blitz::$plugin->settings->maxUriLength + 1));

    expect(Blitz::$plugin->cacheRequest->getIsCacheableSiteUri($siteUri))
        ->toBeFalse();
});

test('URI patterns with matching regular expressions are matched', function () {
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

test('URI patterns without matching regular expressions are not matched', function () {
    $matchesUriPatterns = Blitz::$plugin->cacheRequest->matchesUriPatterns(
        createSiteUri(),
        [['siteId' => 1, 'uriPattern' => '^my-page$']]
    );
    expect($matchesUriPatterns)
        ->toBeFalse();
});
