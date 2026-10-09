<?php

/**
 * Tests cache API authentication and control panel permissions.
 */

use markhuot\craftpest\http\requests\PostRequest;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\controllers\CacheController;
use yii\base\Action;
use yii\web\ForbiddenHttpException;

beforeEach(function() {
    $this->originalRequest = Craft::$app->getRequest();
    $this->originalApiKey = Blitz::$plugin->settings->apiKey;
    Blitz::$plugin->settings->apiKey = 'aB7cD9eF2gH4jK6m';

    $request = PostRequest::make('actions/blitz/cache/refresh');
    $request->setIsCpRequest(false);
    $request->setQueryParams([]);
    Craft::$app->set('request', $request);
});

afterEach(function() {
    Craft::$app->set('request', $this->originalRequest);
    Blitz::$plugin->settings->apiKey = $this->originalApiKey;
});

test('Cache API authenticates valid keys and rejects invalid or malformed keys', function(array $query, array $body, array $headers, string $apiKey, bool $accepted) {
    $request = Craft::$app->getRequest();
    $request->setQueryParams($query);
    $request->setBodyParams($body);
    foreach ($headers as $name => $value) {
        $request->headers->set($name, $value);
    }
    Blitz::$plugin->settings->apiKey = $apiKey;

    $controller = new CacheController('cache', Blitz::$plugin);
    $action = new Action('refresh', $controller);

    if ($accepted) {
        expect($controller->beforeAction($action))->toBeTrue();
    } else {
        expect(fn() => $controller->beforeAction($action))->toThrow(ForbiddenHttpException::class);
    }
})->with([
    'query key' => [['key' => 'aB7cD9eF2gH4jK6m'], [], [], 'aB7cD9eF2gH4jK6m', true],
    'form body key without CSRF or Snaptcha' => [[], ['key' => 'aB7cD9eF2gH4jK6m'], [], 'aB7cD9eF2gH4jK6m', true],
    'authorization header' => [[], [], ['Authorization' => 'Bearer aB7cD9eF2gH4jK6m'], 'aB7cD9eF2gH4jK6m', true],
    'Craft authorization header' => [[], [], ['X-Craft-Authorization' => 'Bearer aB7cD9eF2gH4jK6m'], 'aB7cD9eF2gH4jK6m', true],
    'case insensitive Bearer scheme' => [[], [], ['Authorization' => 'bearer aB7cD9eF2gH4jK6m'], 'aB7cD9eF2gH4jK6m', true],
    'Bearer overrides wrong parameter' => [['key' => 'wrong'], [], ['Authorization' => 'Bearer aB7cD9eF2gH4jK6m'], 'aB7cD9eF2gH4jK6m', true],
    'wrong Bearer cannot fall back to valid parameter' => [[], ['key' => 'aB7cD9eF2gH4jK6m'], ['Authorization' => 'Bearer wrong'], 'aB7cD9eF2gH4jK6m', false],
    'Craft header takes precedence' => [[], [], ['X-Craft-Authorization' => 'Bearer wrong', 'Authorization' => 'Bearer aB7cD9eF2gH4jK6m'], 'aB7cD9eF2gH4jK6m', false],
    'query takes precedence over body' => [['key' => 'wrong'], ['key' => 'aB7cD9eF2gH4jK6m'], [], 'aB7cD9eF2gH4jK6m', false],
    'missing key' => [[], [], [], 'aB7cD9eF2gH4jK6m', false],
    'empty key' => [[], ['key' => ''], [], 'aB7cD9eF2gH4jK6m', false],
    'wrong key' => [[], ['key' => 'wrong'], [], 'aB7cD9eF2gH4jK6m', false],
    'null key' => [[], ['key' => null], [], 'aB7cD9eF2gH4jK6m', false],
    'boolean true key' => [[], ['key' => true], [], 'aB7cD9eF2gH4jK6m', false],
    'boolean false key' => [[], ['key' => false], [], 'aB7cD9eF2gH4jK6m', false],
    'integer key' => [[], ['key' => 1234567890123456], [], '1234567890123456', false],
    'float key' => [[], ['key' => 1.0], [], 'aB7cD9eF2gH4jK6m', false],
    'array key' => [[], ['key' => ['aB7cD9eF2gH4jK6m']], [], 'aB7cD9eF2gH4jK6m', false],
    'numeric string collision' => [[], ['key' => '0e98765432109876'], [], '0e12345678901234', false],
    'exact numeric string key' => [[], ['key' => '0e12345678901234'], [], '0e12345678901234', true],
    'disabled API key' => [[], ['key' => 'aB7cD9eF2gH4jK6m'], [], '', false],
    'disabled API key with Bearer token' => [[], [], ['Authorization' => 'Bearer aB7cD9eF2gH4jK6m'], '', false],
    'unsupported authorization scheme' => [[], [], ['Authorization' => 'Basic aB7cD9eF2gH4jK6m'], 'aB7cD9eF2gH4jK6m', false],
    'empty Bearer token' => [[], [], ['Authorization' => 'Bearer '], 'aB7cD9eF2gH4jK6m', false],
]);

test('Cache API rejects a boolean key parsed from a JSON request', function() {
    $request = Craft::$app->getRequest();
    $request->headers->set('Content-Type', 'application/json');
    $request->setBody('{"key":true}');

    expect($request->getParam('key'))->toBeTrue();

    $controller = new CacheController('cache', Blitz::$plugin);
    expect(fn() => $controller->beforeAction(new Action('refresh', $controller)))
        ->toThrow(ForbiddenHttpException::class);
});

test('Cache API resolves environment keys and rejects non-string configured keys', function(string $value, bool $accepted) {
    $name = 'BLITZ_TEST_CACHE_API_KEY';
    $original = getenv($name);
    putenv($name . '=' . $value);

    try {
        Blitz::$plugin->settings->apiKey = '$' . $name;
        Craft::$app->getRequest()->setBodyParams(['key' => 'aB7cD9eF2gH4jK6m']);
        $controller = new CacheController('cache', Blitz::$plugin);
        $action = new Action('refresh', $controller);

        if ($accepted) {
            expect($controller->beforeAction($action))->toBeTrue();
        } else {
            expect(fn() => $controller->beforeAction($action))->toThrow(ForbiddenHttpException::class);
        }
    } finally {
        putenv($original === false ? $name : $name . '=' . $original);
    }
})->with([
    'string environment key' => ['aB7cD9eF2gH4jK6m', true],
    'empty environment key' => ['', false],
    'boolean environment key' => ['true', false],
]);

test('Control panel POST requests still require the action permission', function() {
    Craft::$app->getRequest()->setIsCpRequest(true);
    $controller = Mockery::mock(CacheController::class . '[requirePermission]', ['cache', Blitz::$plugin]);
    $controller->shouldReceive('requirePermission')->with('blitz:refresh')->once();

    expect($controller->beforeAction(new Action('refresh', $controller)))->toBeTrue();
});

test('Control panel POST requests cannot bypass a denied action permission with an API key', function() {
    Craft::$app->getRequest()->setIsCpRequest(true);
    Craft::$app->getRequest()->headers->set('Authorization', 'Bearer aB7cD9eF2gH4jK6m');
    $controller = Mockery::mock(CacheController::class . '[requirePermission]', ['cache', Blitz::$plugin]);
    $controller->shouldReceive('requirePermission')->with('blitz:refresh')->once()->andThrow(ForbiddenHttpException::class);

    expect(fn() => $controller->beforeAction(new Action('refresh', $controller)))
        ->toThrow(ForbiddenHttpException::class);
});
