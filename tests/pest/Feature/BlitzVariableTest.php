<?php

/**
 * Tests the markup generated by the Blitz variable.
 */

use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\variables\BlitzVariable;

beforeEach(function() {
    Craft::$app->getView()->js = [];
});

test('Cached include tag contains provided options', function() {
    Blitz::$plugin->settings->ssiEnabled = true;
    $variable = new BlitzVariable();
    $tagString = (string)$variable->includeCached('test', [], [
        'requestType' => 'ajax',
        'wrapperElement' => 'div',
        'wrapperClass' => 'test',
        'placeholder' => 'Loading...',
        'property' => 'test',
    ]);

    expect($tagString)
        ->toContain(
            '<div',
            'class="test blitz-inject"',
            'data-blitz-property="test"',
            'Loading...',
        );
});

test('Cached include tag does not contain unencoded slashes in params', function() {
    $variable = new BlitzVariable();
    $tagString = (string)$variable->includeCached('test');
    preg_match('/_includes\?(.*)/', $tagString, $match);

    expect($match[1])
        ->not->toContain('/');
});

test('Cached include tag does not contain path param', function() {
    $variable = new BlitzVariable();
    $tagString = (string)$variable->includeCached('test');
    preg_match('/\?(.*)/', $tagString, $match);

    expect($match[1])
        ->not->toContain(Craft::$app->getConfig()->getGeneral()->pathParam . '=');
});

test('Cached include tag with AJAX request type results in inject script being registered', function() {
    $variable = new BlitzVariable();
    $variable->includeCached('test', [], [
        'requestType' => 'ajax',
    ]);

    expect(Craft::$app->getView()->js[Blitz::$plugin->settings->injectScriptPosition])
        ->toHaveCount(1);
});

test('Uniquely cached include tag contains a `uid` and results in inject script being registered', function() {
    $variable = new BlitzVariable();
    $output = (string)$variable->includeCachedUnique('test');

    expect(Craft::$app->getView()->js[Blitz::$plugin->settings->injectScriptPosition])
        ->toHaveCount(1)
        ->and($output)
        ->toContain('&amp;uid=0');
});

test('Dynamic include tag results in inject script being registered', function() {
    $variable = new BlitzVariable();
    $variable->includeDynamic('test');

    expect(Craft::$app->getView()->js[Blitz::$plugin->settings->injectScriptPosition])
        ->toHaveCount(1);
});

test('Fetch URI tag does not contain unencoded slashes in params', function() {
    $variable = new BlitzVariable();
    $tagString = (string)$variable->fetchUri('test', ['action' => 'x/y/z']);
    preg_match('/blitz-params="(.*?)"/', $tagString, $match);

    expect($match[1])
        ->not->toContain('/');
});

test('The CSRF input function returns a Blitz inject script', function() {
    $variable = new BlitzVariable();
    $csrfInput = (string)$variable->csrfInput();

    expect($csrfInput)
        ->toContain('id="blitz-inject-1"');
});

test('The CSRF input function called in an AJAX request does not return a Blitz inject script', function() {
    Craft::$app->getRequest()->getHeaders()->set('X-Requested-With', 'XMLHttpRequest');
    $variable = new BlitzVariable();
    $csrfInput = (string)$variable->csrfInput();

    expect($csrfInput)
        ->not->toContain('id="blitz-inject-1"');
});
