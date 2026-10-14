<?php

/**
 * Tests cache API authentication against malformed keys and loose comparison attacks.
 */

use craft\web\Request;
use craft\web\View;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\controllers\CacheController;
use yii\base\Action;
use yii\web\ForbiddenHttpException;

beforeEach(function() {
    $this->originalRequest = Craft::$app->getRequest();
    $this->originalApiKey = Blitz::$plugin->settings->apiKey;

    $request = Mockery::mock(Request::class . '[getIsPost,getIsCpRequest]');
    $request->parsers = ['application/json' => yii\web\JsonParser::class];
    $request->shouldReceive('getIsPost')->andReturn(true);
    $request->shouldReceive('getIsCpRequest')->andReturn(false);
    $request->setQueryParams([]);
    Craft::$app->set('request', $request);
});

test('Cache API authenticates Bearer headers and gives them precedence over parameters', function(string $header, string $token, string $key, bool $accepted) {
    $request = Craft::$app->getRequest();
    $request->headers->set($header, 'Bearer ' . $token);
    $request->setBodyParams(['key' => $key]);
    Blitz::$plugin->settings->apiKey = 'secret';
    $controller = new CacheController('cache', Blitz::$plugin);
    $action = new Action('refresh', $controller);

    if ($accepted) {
        expect($controller->beforeAction($action))->toBeTrue();
    } else {
        expect(fn() => $controller->beforeAction($action))->toThrow(ForbiddenHttpException::class);
    }
})->with([
    'authorization' => ['Authorization', 'secret', '', true],
    'Craft authorization' => ['X-Craft-Authorization', 'secret', '', true],
    'valid header overrides wrong parameter' => ['Authorization', 'secret', 'wrong', true],
    'wrong header cannot fall back to parameter' => ['Authorization', 'wrong', 'secret', false],
]);

test('Cache API failures return HTTP 400 with the JSON failure payload', function(string $action) {
    $request = Craft::$app->getRequest();
    $request->setBodyParams([]);
    $view = Craft::$app->getView();
    $originalMode = $view->templateMode;
    $response = Craft::$app->getResponse();
    $originalStatus = $response->getStatusCode();
    $originalFormat = $response->format;
    $originalData = $response->data;
    $view->templateMode = View::TEMPLATE_MODE_SITE;

    try {
        $controller = new CacheController('cache', Blitz::$plugin);
        $result = $controller->$action();
        expect($result->getStatusCode())->toBe(400)
            ->and($result->data['success'])->toBeFalse();
    } finally {
        $view->templateMode = $originalMode;
        $response->setStatusCode($originalStatus);
        $response->format = $originalFormat;
        $response->data = $originalData;
    }
})->with(['actionRefreshSite', 'actionRefreshUrls', 'actionRefreshTagged']);

afterEach(function() {
    Craft::$app->set('request', $this->originalRequest);
    Blitz::$plugin->settings->apiKey = $this->originalApiKey;
});

test('Cache API accepts exact strings and rejects malformed or loosely matching keys', function(mixed $key, string $apiKey, bool $accepted, bool $query) {
    $request = Craft::$app->getRequest();
    $request->setQueryParams($query ? ['key' => $key] : []);
    $request->setBodyParams($query ? [] : ['key' => $key]);
    Blitz::$plugin->settings->apiKey = $apiKey;

    $controller = new CacheController('cache', Blitz::$plugin);
    $action = new Action('refresh', $controller);

    if ($accepted) {
        expect($controller->beforeAction($action))->toBeTrue();
    } else {
        expect(fn() => $controller->beforeAction($action))->toThrow(ForbiddenHttpException::class);
    }
})->with([
    'valid query key' => ['secret', 'secret', true, true],
    'valid body key' => ['secret', 'secret', true, false],
    'boolean true' => [true, 'secret', false, false],
    'boolean false' => [false, 'secret', false, false],
    'integer' => [123456789, '123456789', false, false],
    'array' => [['secret'], 'secret', false, true],
    'null' => [null, 'secret', false, false],
    'empty key' => ['', 'secret', false, false],
    'wrong key' => ['wrong', 'secret', false, false],
    'disabled API' => ['secret', '', false, false],
    'numeric string collision' => ['0e987654321', '0e123456789', false, false],
    'exact numeric string' => ['0e123456789', '0e123456789', true, false],
]);

test('Cache API rejects a boolean key parsed from a JSON request', function() {
    $request = Craft::$app->getRequest();
    $request->headers->set('Content-Type', 'application/json');
    $request->setRawBody('{"key":true}');
    Blitz::$plugin->settings->apiKey = 'secret';

    expect($request->getParam('key'))->toBeTrue();

    $controller = new CacheController('cache', Blitz::$plugin);
    expect(fn() => $controller->beforeAction(new Action('refresh', $controller)))
        ->toThrow(ForbiddenHttpException::class);
});

test('Cache API rejects a non-string configured environment key', function() {
    $name = 'BLITZ_TEST_CACHE_API_KEY';
    $original = getenv($name);
    putenv($name . '=true');

    try {
        Blitz::$plugin->settings->apiKey = '$' . $name;
        Craft::$app->getRequest()->setBodyParams(['key' => 'true']);
        $controller = new CacheController('cache', Blitz::$plugin);

        expect(fn() => $controller->beforeAction(new Action('refresh', $controller)))
            ->toThrow(ForbiddenHttpException::class);
    } finally {
        putenv($original === false ? $name : $name . '=' . $original);
    }
});
