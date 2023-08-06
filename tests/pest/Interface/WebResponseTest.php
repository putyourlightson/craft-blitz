<?php

/**
 * Tests that cached web responses contain the correct headers and comments.
 */

use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\models\SettingsModel;

beforeEach(function () {
    Blitz::$plugin->settings->includedUriPatterns = [
        [
            'siteId' => '',
            'uriPattern' => '.*',
        ],
    ];
    Blitz::$plugin->cacheStorage->deleteAll();
});

afterEach(function () {
    Blitz::$plugin->cacheStorage->deleteAll();
});

test('Response adds “powered by” header once', function () {
    Craft::$app->config->general->sendPoweredByHeader = true;
    $response = sendRequest();

    expect($response->headers->get('x-powered-by'))
        ->toContainOnce('Blitz', 'Craft CMS');
});

test('Response overwrites “powered by” header', function () {
    Craft::$app->config->general->sendPoweredByHeader = false;
    $response = sendRequest();

    expect($response->headers->get('x-powered-by'))
        ->toContainOnce('Blitz')
        ->not()->toContain('Craft CMS');
});

test('Response contains output comments when enabled', function () {
    foreach ([true, SettingsModel::OUTPUT_COMMENTS_SERVED] as $value) {
        Blitz::$plugin->settings->outputComments = $value;
        $response = sendRequest();

        expect($response->content)
            ->toContain('Cached by Blitz');
    }
});

test('Response does not contain output comments when disabled', function () {
    foreach ([false, SettingsModel::OUTPUT_COMMENTS_CACHED] as $value) {
        Blitz::$plugin->settings->outputComments = $value;
        $response = sendRequest();

        expect($response->content)
            ->not()->toContain('Served by Blitz on');
    }
});

test('Response with mime type has headers and does not contain output comments', function () {
    $output = createOutput();
    $siteUri = createSiteUri(uri: 'page.json');
    Blitz::$plugin->cacheStorage->save($output, $siteUri);
    $response = Blitz::$plugin->cacheRequest->getCachedResponse($siteUri);

    expect($response->headers->get('Content-Type'))
        ->toBe('application/json')
        ->and($response->content)
        ->toBe($output);
});

test('Response is encoded when compression is enabled', function () {
    $output = createOutput();
    $siteUri = createSiteUri();
    Blitz::$plugin->cacheStorage->compressCachedValues = true;
    Blitz::$plugin->cacheStorage->save($output, $siteUri);
    Craft::$app->getRequest()->headers->set('Accept-Encoding', 'deflate, gzip');
    $response = Blitz::$plugin->cacheRequest->getCachedResponse($siteUri);

    expect($response->headers->get('Content-Encoding'))
        ->toBe('gzip')
        ->and(gzdecode($response->content))
        ->toBe($output);
});

test('Response is not encoded when compression is disabled', function () {
    $output = createOutput();
    $siteUri = createSiteUri();
    Blitz::$plugin->cacheStorage->compressCachedValues = false;
    Blitz::$plugin->cacheStorage->save($output, $siteUri);
    Craft::$app->getRequest()->headers->set('Accept-Encoding', 'deflate, gzip');
    $response = Blitz::$plugin->cacheRequest->getCachedResponse($siteUri);

    expect($response->headers->get('Content-Encoding'))
        ->toBeNull()
        ->and($response->content)
        ->toBe($output);
});
