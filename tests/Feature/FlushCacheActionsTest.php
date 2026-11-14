<?php

/**
 * Tests scoped flushing through console commands and MCP without deleting cached files.
 */

use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\console\controllers\CacheController;
use putyourlightson\blitz\helpers\SiteUriHelper;
use putyourlightson\blitz\mcp\CacheTools;
use putyourlightson\blitz\records\CacheRecord;
use yii\console\ExitCode;

beforeEach(function() {
    $this->controller = Mockery::mock(CacheController::class . '[stdout,stderr]', ['cache', Blitz::$plugin]);
    $this->controller->shouldReceive('stdout')->andReturn(0);
    $this->controller->shouldReceive('stderr')->andReturn(0);
});

test('Scoped flush deletes only selected database records and retains cached files', function(string $interface, string $scope) {
    if ($interface === 'mcp' && !class_exists(\craft\services\Mcp::class)) {
        $this->markTestSkipped('Craft MCP support is not available.');
    }

    $sites = Craft::$app->getSites()->getAllSites();
    if ($scope === 'site' && count($sites) < 2) {
        $this->markTestSkipped('Site isolation requires two sites.');
    }
    $site = reset($sites);
    $otherSite = $scope === 'site' ? next($sites) : $site;
    $prefix = 'flush-test-' . bin2hex(random_bytes(6));
    $siteUris = [
        createSiteUri($site->id, $prefix . '/selected/one'),
        createSiteUri($site->id, $prefix . '/selected/two'),
        createSiteUri($otherSite->id, $prefix . '/retained'),
    ];

    try {
        foreach ($siteUris as $siteUri) {
            Craft::$app->getDb()->createCommand()->insert(CacheRecord::tableName(), $siteUri->toArray())->execute();
            Blitz::$plugin->cacheStorage->save('Retained cached content', $siteUri);
        }
        $arguments = $scope === 'site' ? [$site->id] : [[rtrim($site->getBaseUrl(), '/') . '/' . $prefix . '/selected/*']];
        if ($scope === 'unmatched') {
            $arguments = [[rtrim($site->getBaseUrl(), '/') . '/' . $prefix . '/missing/*']];
        }
        $method = $scope === 'site' ? 'flushSite' : 'flushUrls';
        if ($interface === 'console') {
            $method = 'action' . ucfirst($method);
            expect($this->controller->$method(...$arguments))->toBe(ExitCode::OK);
        } else {
            expect((new CacheTools())->$method(...$arguments)['success'])->toBeTrue();
        }

        foreach ($siteUris as $index => $siteUri) {
            expect(SiteUriHelper::getCacheIdsFromSiteUris([$siteUri]))->toHaveCount($scope === 'unmatched' || $index === 2 ? 1 : 0)
                ->and(Blitz::$plugin->cacheStorage->get($siteUri))->toBe('Retained cached content');
        }
    } finally {
        foreach ($siteUris as $siteUri) {
            CacheRecord::deleteAll($siteUri->toArray());
        }
        Blitz::$plugin->cacheStorage->deleteUris($siteUris);
    }
})->with(['console', 'mcp'])->with(['site', 'urls', 'unmatched']);

test('Console scoped flush rejects missing or invalid arguments without flushing all records', function(string $method, array $arguments) {
    expect($this->controller->$method(...$arguments))->toBe(ExitCode::USAGE);
})->with([
    ['actionFlushSite', []],
    ['actionFlushSite', [-1]],
    ['actionFlushUrls', []],
]);
