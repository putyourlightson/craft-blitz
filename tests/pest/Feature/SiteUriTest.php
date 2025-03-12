<?php

/**
 * Tests the site URI helper methods.
 */

use craft\base\Element;
use putyourlightson\blitz\helpers\SiteUriHelper;
use putyourlightson\blitz\models\SiteUriModel;

test('Site URIs are returned from assets with transforms', function() {
    $asset = createAsset();
    $asset->getUrl(['width' => 30, 'height' => 30], true);
    $siteUris = SiteUriHelper::getAssetSiteUris([$asset->id]);

    expect($siteUris)
        ->toHaveCount(2)
        ->and($siteUris[0]->uri)
        ->toBe('assets/test/' . $asset->filename)
        ->and($siteUris[1]->uri)
        ->toBe('assets/test/_30x30_crop_center-center_none/' . $asset->filename);
});

test('HTML mime type is returned when site URI is HTML', function() {
    expect(SiteUriHelper::getMimeType(createSiteUri()))
        ->toBe('text/html');
});

test('JSON mime type is returned when site URI is JSON', function() {
    expect(SiteUriHelper::getMimeType(createSiteUri(uri: 'page.json')))
        ->toBe('application/json');
});

test('Site URIs with page triggers are paginated', function() {
    Craft::$app->config->general->pageTrigger = 'page';
    expect(SiteUriHelper::isPaginatedUri('page3'))
        ->toBeTrue();

    Craft::$app->config->general->pageTrigger = 'page/';
    expect(SiteUriHelper::isPaginatedUri('page/3'))
        ->toBeTrue();

    Craft::$app->config->general->pageTrigger = '?page=';
    expect(SiteUriHelper::isPaginatedUri('?x=1&page=3'))
        ->toBeTrue();
});

test('Site URIs without page triggers are not paginated', function() {
    Craft::$app->config->general->pageTrigger = 'page';
    expect(SiteUriHelper::isPaginatedUri('page'))
        ->toBeFalse();

    Craft::$app->config->general->pageTrigger = 'page/';
    expect(SiteUriHelper::isPaginatedUri('page3'))
        ->toBeFalse();

    Craft::$app->config->general->pageTrigger = '?page=';
    expect(SiteUriHelper::isPaginatedUri('?page3'))
        ->toBeFalse();
});

test('Site URIs containing the homepage URI are converted to an empty string', function() {
    $siteUri = new SiteUriModel([
        'uri' => Element::HOMEPAGE_URI,
        'siteId' => 1,
    ]);
    expect($siteUri->uri)
        ->toBe('');
});
