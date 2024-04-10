<?php

/**
 * Tests what should happen when, based on the refresh modes.
 */

use putyourlightson\blitz\Blitz;

test('The cache should be cleared on refresh”', function(int $value) {
    Blitz::$plugin->settings->refreshMode = $value;

    expect(Blitz::$plugin->settings->shouldClearOnRefresh())
        ->toBeTrue();
})->with('refresh mode clear');

test('The cache should not be cleared on refresh', function(int $value) {
    Blitz::$plugin->settings->refreshMode = $value;

    expect(Blitz::$plugin->settings->shouldClearOnRefresh())
        ->toBeFalse();
})->with('refresh mode expire');

test('The cache should be cleared on refresh when forcing a clear', function(int $value) {
    Blitz::$plugin->settings->refreshMode = $value;

    expect(Blitz::$plugin->settings->shouldClearOnRefresh(true))
        ->toBeTrue();
})->with('refresh mode clear');

test('The cache should be generated on refresh', function(int $value) {
    Blitz::$plugin->settings->refreshMode = $value;

    expect(Blitz::$plugin->settings->shouldGenerateOnRefresh())
        ->toBeTrue();
})->with('refresh mode generate');

test('The cache should not be generated on refresh', function(int $value) {
    Blitz::$plugin->settings->refreshMode = $value;

    expect(Blitz::$plugin->settings->shouldGenerateOnRefresh())
        ->toBeFalse();
})->with('refresh mode manual');

test('The cache should be generated on refresh when forcing a generate', function(int $value) {
    Blitz::$plugin->settings->refreshMode = $value;

    expect(Blitz::$plugin->settings->shouldGenerateOnRefresh(true))
        ->toBeTrue();
})->with('refresh mode manual');

test('The cache should be expired on refresh', function(int $value) {
    Blitz::$plugin->settings->refreshMode = $value;

    expect(Blitz::$plugin->settings->shouldExpireOnRefresh())
        ->toBeTrue();
})->with('refresh mode expire');

test('The cache should not be expired on refresh', function(int $value) {
    Blitz::$plugin->settings->refreshMode = $value;

    expect(Blitz::$plugin->settings->shouldExpireOnRefresh())
        ->toBeFalse();
})->with('refresh mode clear');

test('The cache should not be expired on refresh when forcing a generate', function(int $value) {
    Blitz::$plugin->settings->refreshMode = $value;

    expect(Blitz::$plugin->settings->shouldExpireOnRefresh(false, true))
        ->toBeFalse();
})->with('refresh mode expire');

test('The cache should be purged after refresh', function(int $value) {
    Blitz::$plugin->settings->refreshMode = $value;

    expect(Blitz::$plugin->settings->shouldPurgeAfterRefresh())
        ->toBeTrue();
})->with('refresh mode expire');

test('The cache should not be purged after refresh', function(int $value) {
    Blitz::$plugin->settings->refreshMode = $value;

    expect(Blitz::$plugin->settings->shouldPurgeAfterRefresh())
        ->toBeFalse();
})->with('refresh mode clear');

test('The cache should not be purged after refresh when forcing a clear', function(int $value) {
    Blitz::$plugin->settings->refreshMode = $value;

    expect(Blitz::$plugin->settings->shouldPurgeAfterRefresh(true))
        ->toBeFalse();
})->with('refresh mode expire');
