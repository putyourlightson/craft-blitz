<?php

/**
 * Tests Cloudflare purging and refresh ordering with a single queue worker.
 */

use craft\helpers\StringHelper;
use craft\queue\Queue;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\drivers\deployers\BaseDeployer;
use putyourlightson\blitz\drivers\generators\HttpGenerator;
use putyourlightson\blitz\drivers\purgers\CloudflarePurger;
use putyourlightson\blitz\events\RefreshCacheEvent;
use putyourlightson\blitz\jobs\PurgeSiteJob;
use putyourlightson\blitz\models\SettingsModel;
use putyourlightson\blitz\models\SiteUriModel;
use putyourlightson\blitz\services\RefreshCacheService;
use yii\queue\PushEvent;

beforeEach(function() {
    $this->originalQueue = Blitz::$plugin->queue;
    $this->originalSettings = clone Blitz::$plugin->settings;
    $this->originalDrivers = [];
    foreach (['cachePurger', 'cacheGenerator', 'deployer', 'refreshCache'] as $id) {
        $this->originalDrivers[$id] = Blitz::$plugin->get($id);
    }
    $this->originalClientDefinition = Craft::$container->getDefinitions()[Client::class] ?? null;
    $this->requests = [];
    $this->operations = [];
    $this->jobs = [];

    $this->queue = new Queue(['channel' => 'blitz-cloudflare-test-' . StringHelper::randomString(10)]);
    Blitz::$plugin->queue = $this->queue;
    $this->queue->on(Queue::EVENT_AFTER_PUSH, function(PushEvent $event) {
        $this->jobs[] = $event->job;
    });

    $client = Mockery::mock(Client::class);
    $client->shouldReceive('request')->andReturnUsing(function($method, $uri, $options) {
        $this->requests[] = [$method, $uri, $options];
        $this->operations[] = 'purge';

        return new Response(200);
    });
    Craft::$container->set(Client::class, fn() => $client);

    $zoneIds = [];
    foreach (Craft::$app->getSites()->getAllSites() as $site) {
        $zoneIds[$site->uid] = ['zoneId' => 'test-zone-' . $site->id];
    }
    Blitz::$plugin->set('cachePurger', new CloudflarePurger(['zoneIds' => $zoneIds]));
});

afterEach(function() {
    $this->queue->releaseAll();
    Blitz::$plugin->queue = $this->originalQueue;
    foreach ($this->originalDrivers as $id => $driver) {
        Blitz::$plugin->set($id, $driver);
    }
    Blitz::$plugin->setSettings($this->originalSettings->toArray());
    Craft::$container->clear(Client::class);
    if ($this->originalClientDefinition !== null) {
        Craft::$container->set(Client::class, $this->originalClientDefinition);
    }
});

test('Cloudflare site purges honor the queue flag', function(bool $queue) {
    $siteId = Craft::$app->getSites()->getPrimarySite()->id;
    Blitz::$plugin->cachePurger->purgeSite($siteId, queue: $queue);

    expect($this->requests)->toHaveCount($queue ? 0 : 1)
        ->and($this->jobs)->toHaveCount($queue ? 1 : 0);

    if ($queue) {
        expect($this->jobs[0])->toBeInstanceOf(PurgeSiteJob::class)
            ->and($this->jobs[0]->siteId)->toBe($siteId);
        $this->queue->run();
    }

    expect($this->requests)->toBe([
        ['delete', 'zones/test-zone-' . $siteId . '/purge_cache', ['json' => ['purge_everything' => true]]],
    ])->and($this->jobs)->toHaveCount($queue ? 1 : 0);
})->with([true, false]);

test('Refresh completes HTTP generation batches and deployment before purging Cloudflare', function(int $mode, bool $fullRefresh) {
    $settings = Blitz::$plugin->settings;
    $settings->cachingEnabled = true;
    $settings->refreshMode = $mode;
    $settings->driverJobBatchSize = 1;
    $settings->driverJobPriority = 100;
    $siteId = Craft::$app->getSites()->getPrimarySite()->id;
    $siteUris = array_map(fn($uri) => new SiteUriModel(['siteId' => $siteId, 'uri' => $uri]), ['first', 'second', 'third']);
    $settings->customSiteUris = array_map(fn(SiteUriModel $siteUri) => $siteUri->toArray(), $siteUris);

    $generatedCount = 0;
    $generator = Mockery::mock(HttpGenerator::class . '[generateUrisWithProgress]');
    $generator->on(HttpGenerator::EVENT_BEFORE_GENERATE_CACHE, function(RefreshCacheEvent $event) use (&$generatedCount) {
        $generatedCount = count($event->siteUris);
    });
    $generator->shouldReceive('generateUrisWithProgress')->andReturnUsing(function($batch) {
        expect($batch)->toHaveCount(1);
        $this->operations[] = 'generate';
    });
    Blitz::$plugin->set('cacheGenerator', $generator);

    $deployer = Mockery::mock(BaseDeployer::class . '[deployUrisWithProgress]');
    $deployer->shouldReceive('deployUrisWithProgress')->once()->andReturnUsing(function() {
        $this->operations[] = 'deploy';
    });
    Blitz::$plugin->set('deployer', $deployer);

    // Keep a full refresh from releasing unrelated jobs on the app's queue.
    $refreshCache = Mockery::mock(RefreshCacheService::class . '[releaseJobs]');
    $refreshCache->shouldReceive('releaseJobs')->zeroOrMoreTimes();
    Blitz::$plugin->set('refreshCache', $refreshCache);

    if ($fullRefresh) {
        $refreshCache->refreshAll();
    } else {
        $refreshCache->refreshSiteUris($siteUris);
    }

    expect($generatedCount)->toBeGreaterThanOrEqual(3)
        ->and($this->operations)->toBeEmpty();

    $this->queue->run();

    expect($this->operations)->toBe(array_merge(
        array_fill(0, $generatedCount, 'generate'),
        ['deploy'],
        array_fill(0, $fullRefresh ? count(Craft::$app->getSites()->getAllSites()) : 1, 'purge'),
    ));
})->with([
    'clear and generate' => SettingsModel::REFRESH_MODE_CLEAR_AND_GENERATE,
    'expire and generate' => SettingsModel::REFRESH_MODE_EXPIRE_AND_GENERATE,
])->with([
    'full refresh' => true,
    'URI refresh' => false,
]);
