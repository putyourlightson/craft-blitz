<?php

use craft\db\Connection;
use craft\db\Query;
use craft\helpers\Db;
use craft\queue\Queue;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\drivers\deployers\BaseDeployer;
use putyourlightson\blitz\drivers\generators\BaseCacheGenerator;
use putyourlightson\blitz\drivers\purgers\BaseCachePurger;
use putyourlightson\blitz\helpers\BaseDriverHelper;
use putyourlightson\blitz\helpers\CacheGeneratorHelper;
use putyourlightson\blitz\helpers\CachePurgerHelper;
use putyourlightson\blitz\helpers\DeployerHelper;
use putyourlightson\blitz\helpers\DriverQueueHelper;
use putyourlightson\blitz\jobs\DriverJob;
use putyourlightson\blitz\jobs\GenerateCacheJob;
use putyourlightson\blitz\jobs\PurgeSiteJob;
use putyourlightson\blitz\migrations\Install;
use putyourlightson\blitz\migrations\m261118_120000_add_driverqueue_table;
use putyourlightson\blitz\models\SettingsModel;
use putyourlightson\blitz\models\SiteUriModel;
use putyourlightson\blitz\records\DriverQueueRecord;
use putyourlightson\blitz\records\QueueSiteUriRecord;

uses()->group('all');

beforeEach(function() {
    $this->transaction = Craft::$app->getDb()->beginTransaction();
    $this->settings = Blitz::$plugin->settings->getAttributes();
    $this->originalQueue = Blitz::$plugin->queue;
    $this->queue = new Queue(['channel' => 'blitz-work-' . bin2hex(random_bytes(6))]);
    Blitz::$plugin->queue = $this->queue;
    $this->components = [];
    foreach (['cacheGenerator', 'deployer', 'cachePurger'] as $id) {
        $this->components[$id] = Blitz::$plugin->get($id);
    }
    $this->generator = new class() extends BaseCacheGenerator {
        public array $batches = [];
        public ?Closure $onGenerate = null;

        public function generateUrisWithProgress(array $siteUris, ?callable $setProgressHandler = null): void
        {
            $this->batches[] = array_map(fn($siteUri) => $siteUri->uri, $siteUris);
            if ($this->onGenerate !== null) {
                ($this->onGenerate)();
            }
        }
    };
    $this->deployer = new class() extends BaseDeployer {
        public array $batches = [];

        public function deployUrisWithProgress(array $siteUris, ?callable $setProgressHandler = null): void
        {
            $this->batches[] = array_map(fn($siteUri) => $siteUri->uri, $siteUris);
        }
    };
    $this->purger = new class() extends BaseCachePurger {
        public array $batches = [];

        public function purgeUrisWithProgress(array $siteUris, ?callable $setProgressHandler = null): void
        {
            $this->batches[] = array_map(fn($siteUri) => $siteUri->uri, $siteUris);
        }
    };
    Blitz::$plugin->set('cacheGenerator', $this->generator);
    Blitz::$plugin->set('deployer', $this->deployer);
    Blitz::$plugin->set('cachePurger', $this->purger);
    Blitz::$plugin->settings->cachingEnabled = true;
    Blitz::$plugin->settings->refreshMode = SettingsModel::REFRESH_MODE_CLEAR_AND_GENERATE;
    Blitz::$plugin->settings->driverJobBatchSize = 2;
    Db::delete(DriverQueueRecord::tableName());
    Db::delete(QueueSiteUriRecord::tableName());
    $siteId = Craft::$app->getSites()->getPrimarySite()->id;
    $this->uris = array_map(fn($uri) => new SiteUriModel(['siteId' => $siteId, 'uri' => $uri]), ['work-a', 'work-b', 'work-c']);
    $this->drain = function() {
        $errors = [];
        $this->queue->on(Queue::EVENT_AFTER_ERROR, function($event) use (&$errors) {
            $errors[] = $event->error->getMessage();
        });
        for ($i = 0; $i < 30 && $this->queue->executeJob(); $i++) {
            expect($errors)->toBe([]);
        }
        expect($i)->toBeLessThan(30);
    };
});

afterEach(function() {
    $this->transaction->rollBack();
    foreach ($this->components as $id => $component) {
        Blitz::$plugin->set($id, $component);
    }
    Blitz::$plugin->queue = $this->originalQueue;
    Blitz::$plugin->settings->setAttributes($this->settings, false);
});

test('Overlapping refreshes generate, purge and deploy each pending URL once', function(int $mode) {
    Blitz::$plugin->settings->refreshMode = $mode;
    [$a, $b, $c] = $this->uris;
    Blitz::$plugin->refreshCache->refreshSiteUris([$a, $b]);
    Blitz::$plugin->refreshCache->refreshSiteUris([$b, $c, $a]);

    foreach ([[CacheGeneratorHelper::DRIVER_ID, 'generateUrisWithProgress'], [CachePurgerHelper::DRIVER_ID, 'purgeUrisWithProgress'], [DeployerHelper::DRIVER_ID, 'deployUrisWithProgress']] as [$driverId, $driverMethod]) {
        expect(DriverQueueHelper::getPendingCount($driverId, $driverMethod))->toBe(3);
    }
    expect((int)QueueSiteUriRecord::find()->count())->toBe(3);

    ($this->drain)();

    expect($this->generator->batches)->toBe([['work-a', 'work-b'], ['work-c']])
        ->and($this->deployer->batches)->toBe([['work-a', 'work-b', 'work-c']])
        ->and($this->purger->batches)->toBe([['work-a', 'work-b', 'work-c']])
        ->and((int)QueueSiteUriRecord::find()->count())->toBe(0);
})->with([SettingsModel::REFRESH_MODE_CLEAR_AND_GENERATE, SettingsModel::REFRESH_MODE_EXPIRE_AND_GENERATE]);

test('Duplicate requests retain input order and promote their priority', function() {
    [$a, $b, $c] = $this->uris;
    DriverQueueHelper::add(CacheGeneratorHelper::DRIVER_ID, 'generateUrisWithProgress', [$b, $a, $b], 100);
    DriverQueueHelper::add(CacheGeneratorHelper::DRIVER_ID, 'generateUrisWithProgress', [$c, $a], 10);
    DriverQueueHelper::add(CacheGeneratorHelper::DRIVER_ID, 'generateUrisWithProgress', [$a], 200);
    $result = [];
    DriverQueueHelper::process(CacheGeneratorHelper::DRIVER_ID, 'generateUrisWithProgress', function($siteUris) use (&$result) {
        $result = array_map(fn($siteUri) => $siteUri->uri, $siteUris);
    });
    expect($result)->toBe(['work-a', 'work-c', 'work-b']);
});

test('Shared identities distinguish sites, case, accents and long URI suffixes', function() {
    $siteIds = Craft::$app->getSites()->getAllSiteIds();
    expect($siteIds)->toHaveCount(2);
    $prefix = str_repeat('a', 300);
    $uris = [
        ['siteId' => $siteIds[0], 'uri' => 'page'],
        ['siteId' => $siteIds[1], 'uri' => 'page'],
        ['siteId' => $siteIds[0], 'uri' => 'Page'],
        ['siteId' => $siteIds[0], 'uri' => 'páge'],
        ['siteId' => $siteIds[0], 'uri' => $prefix . 'a'],
        ['siteId' => $siteIds[0], 'uri' => $prefix . 'b'],
    ];
    DriverQueueHelper::add(CacheGeneratorHelper::DRIVER_ID, 'generateUrisWithProgress', array_merge($uris, $uris));
    $result = [];
    DriverQueueHelper::process(CacheGeneratorHelper::DRIVER_ID, 'generateUrisWithProgress', function($siteUris) use (&$result) {
        $result = array_map(fn($siteUri) => $siteUri->toArray(), $siteUris);
    });
    expect($result)->toBe($uris);
});

test('Large refreshes enqueue in bounded chunks without losing order', function() {
    $siteId = $this->uris[0]->siteId;
    $uris = array_map(fn($i) => ['siteId' => $siteId, 'uri' => 'bulk-' . $i], range(1, 1200));
    DriverQueueHelper::add(CacheGeneratorHelper::DRIVER_ID, 'generateUrisWithProgress', array_merge($uris, $uris));
    DriverQueueHelper::add(CacheGeneratorHelper::DRIVER_ID, 'generateUrisWithProgress', $uris);
    $result = [];
    DriverQueueHelper::process(CacheGeneratorHelper::DRIVER_ID, 'generateUrisWithProgress', function($siteUris) use (&$result) {
        $result = array_map(fn($siteUri) => $siteUri->toArray(), $siteUris);
    });
    expect($result)->toBe($uris)
        ->and((int)QueueSiteUriRecord::find()->count())->toBe(0);
});

test('Requests arriving during execution survive completion of the older revision', function(string $driverId, string $driverMethod) {
    [$a] = $this->uris;
    DriverQueueHelper::add($driverId, $driverMethod, [$a]);
    DriverQueueHelper::process($driverId, $driverMethod, function() use ($driverId, $driverMethod, $a) {
        DriverQueueHelper::add($driverId, $driverMethod, [$a]);
    });
    expect(DriverQueueHelper::getPendingPriority($driverId, $driverMethod))->not->toBeNull();
    $calls = 0;
    DriverQueueHelper::process($driverId, $driverMethod, function() use (&$calls) {
        $calls++;
    });
    expect($calls)->toBe(1)
        ->and(DriverQueueHelper::getPendingPriority($driverId, $driverMethod))->toBeNull()
        ->and((int)QueueSiteUriRecord::find()->count())->toBe(0);
})->with([[CacheGeneratorHelper::DRIVER_ID, 'generateUrisWithProgress'], [CachePurgerHelper::DRIVER_ID, 'purgeUrisWithProgress'], [DeployerHelper::DRIVER_ID, 'deployUrisWithProgress']]);

test('Failed work remains pending for retry', function(string $driverId, string $driverMethod) {
    DriverQueueHelper::add($driverId, $driverMethod, [$this->uris[0]]);
    expect(fn() => DriverQueueHelper::process($driverId, $driverMethod, function() {
        throw new RuntimeException('Driver failed');
    }))->toThrow(RuntimeException::class, 'Driver failed');
    expect(DriverQueueHelper::getPendingPriority($driverId, $driverMethod))->not->toBeNull();
    expect(DriverQueueHelper::process($driverId, $driverMethod, fn() => null))->toBe(1);
})->with([[CacheGeneratorHelper::DRIVER_ID, 'generateUrisWithProgress'], [CachePurgerHelper::DRIVER_ID, 'purgeUrisWithProgress'], [DeployerHelper::DRIVER_ID, 'deployUrisWithProgress']]);

test('Deployment waits for generation and expire-mode purging waits for both', function() {
    Blitz::$plugin->settings->refreshMode = SettingsModel::REFRESH_MODE_EXPIRE_AND_GENERATE;
    foreach ([[CacheGeneratorHelper::DRIVER_ID, 'generateUrisWithProgress'], [CachePurgerHelper::DRIVER_ID, 'purgeUrisWithProgress'], [DeployerHelper::DRIVER_ID, 'deployUrisWithProgress']] as [$driverId, $driverMethod]) {
        DriverQueueHelper::add($driverId, $driverMethod, [$this->uris[0]]);
    }
    $unexpected = function() {
        throw new RuntimeException('Work ran before its prerequisite');
    };
    expect(DriverQueueHelper::process(DeployerHelper::DRIVER_ID, 'deployUrisWithProgress', $unexpected))->toBe(0)
        ->and(DriverQueueHelper::process(CachePurgerHelper::DRIVER_ID, 'purgeUrisWithProgress', $unexpected))->toBe(0);
    DriverQueueHelper::process(CacheGeneratorHelper::DRIVER_ID, 'generateUrisWithProgress', fn() => null);
    expect(DriverQueueHelper::process(CachePurgerHelper::DRIVER_ID, 'purgeUrisWithProgress', $unexpected))->toBe(0);
    DriverQueueHelper::process(DeployerHelper::DRIVER_ID, 'deployUrisWithProgress', fn() => null);
    expect(DriverQueueHelper::process(CachePurgerHelper::DRIVER_ID, 'purgeUrisWithProgress', fn() => null))->toBe(1);
});

test('Clear-mode purging does not wait for generation', function() {
    DriverQueueHelper::add(CacheGeneratorHelper::DRIVER_ID, 'generateUrisWithProgress', [$this->uris[0]]);
    DriverQueueHelper::add(CachePurgerHelper::DRIVER_ID, 'purgeUrisWithProgress', [$this->uris[0]]);
    expect(DriverQueueHelper::process(CachePurgerHelper::DRIVER_ID, 'purgeUrisWithProgress', fn() => null))->toBe(1)
        ->and(DriverQueueHelper::getPendingPriority(CacheGeneratorHelper::DRIVER_ID, 'generateUrisWithProgress'))->not->toBeNull();
});

test('Deployment and expire-mode purging wait for related pages with different URIs', function() {
    Blitz::$plugin->settings->refreshMode = SettingsModel::REFRESH_MODE_EXPIRE_AND_GENERATE;
    [$include, $parent] = $this->uris;
    DriverQueueHelper::add(CacheGeneratorHelper::DRIVER_ID, 'generateUrisWithProgress', [$include]);
    DriverQueueHelper::add(DeployerHelper::DRIVER_ID, 'deployUrisWithProgress', [$parent]);
    DriverQueueHelper::add(CachePurgerHelper::DRIVER_ID, 'purgeUrisWithProgress', [$parent]);
    expect(DriverQueueHelper::process(DeployerHelper::DRIVER_ID, 'deployUrisWithProgress', fn() => null))->toBe(0)
        ->and(DriverQueueHelper::process(CachePurgerHelper::DRIVER_ID, 'purgeUrisWithProgress', fn() => null))->toBe(0);
    DriverQueueHelper::process(CacheGeneratorHelper::DRIVER_ID, 'generateUrisWithProgress', fn() => null);
    DriverQueueHelper::process(DeployerHelper::DRIVER_ID, 'deployUrisWithProgress', fn() => null);
    expect(DriverQueueHelper::process(CachePurgerHelper::DRIVER_ID, 'purgeUrisWithProgress', fn() => null))->toBe(1);
});

test('Install and upgrade migrations define the same two queue tables', function() {
    $definitions = [];
    $workTables = [QueueSiteUriRecord::tableName(), DriverQueueRecord::tableName()];
    foreach ([Install::class, m261118_120000_add_driverqueue_table::class] as $class) {
        $db = Mockery::mock(Connection::class . '[tableExists]', [['dsn' => Craft::$app->getDb()->dsn]]);
        $db->shouldReceive('tableExists')->andReturnUsing(fn($table) => !in_array($table, $workTables, true));
        $migration = Mockery::mock($class . '[createTable,createIndex,addForeignKey]', [['db' => $db]]);
        $migration->shouldReceive('createTable')->andReturnUsing(function($table, $columns) use (&$definitions, $class) {
            $definitions[$class]['tables'][$table] = array_map(fn($column) => (string)$column, $columns);
        });
        $migration->shouldReceive('createIndex')->andReturnUsing(function($name, $table, $columns, $unique = false) use (&$definitions, $class, $workTables) {
            if (in_array($table, $workTables, true)) {
                $definitions[$class]['indexes'][] = [$table, $columns, $unique];
            }
        });
        $migration->shouldReceive('addForeignKey')->andReturnUsing(function($name, $table, $columns, $refTable, $refColumns, $delete = null, $update = null) use (&$definitions, $class, $workTables) {
            if (in_array($table, $workTables, true)) {
                $definitions[$class]['keys'][] = [$table, $columns, $refTable, $refColumns, $delete, $update];
            }
        });
        $migration->safeUp();
    }
    expect(array_keys($definitions[Install::class]['tables']))->toBe($workTables)
        ->and($definitions[Install::class])->toBe($definitions[m261118_120000_add_driverqueue_table::class]);
});

test('The work-table upgrade skips tables that already exist', function() {
    $migration = Mockery::mock(m261118_120000_add_driverqueue_table::class . '[createTable,createIndex,addForeignKey]');
    $migration->shouldNotReceive('createTable');
    $migration->shouldNotReceive('createIndex');
    $migration->shouldNotReceive('addForeignKey');

    expect($migration->safeUp())->toBeTrue();
});

test('Synchronous driver calls execute directly and leave queued work untouched', function(string $driver) {
    [$a, $b] = $this->uris;
    $component = Blitz::$plugin->get($driver);
    $method = match ($driver) {
        'cacheGenerator' => 'generateUris',
        'deployer' => 'deployUris',
        'cachePurger' => 'purgeUris',
    };
    $component->$method([$a, $b]);
    $component->$method([$a], queue: false);
    expect($component->batches)->toBe([['work-a']])
        ->and(DriverQueueHelper::getPendingCount($driver, $method . 'WithProgress'))->toBe(2);
    ($this->drain)();
    expect($component->batches)->toBe([['work-a'], ['work-a', 'work-b']]);
})->with(['cacheGenerator', 'deployer', 'cachePurger']);

test('Generation requested during a running batch is generated again', function() {
    [$a] = $this->uris;
    $this->generator->onGenerate = function() use ($a) {
        $this->generator->onGenerate = null;
        $this->generator->generateUris([$a]);
    };
    $this->generator->generateUris([$a]);
    ($this->drain)();
    expect($this->generator->batches)->toBe([['work-a'], ['work-a']]);
});

test('Generation descriptions match Craft’s batch wording', function(int $count, string $description) {
    $uris = array_slice($this->uris, 0, $count);
    DriverQueueHelper::add(CacheGeneratorHelper::DRIVER_ID, 'generateUrisWithProgress', array_merge($uris, $uris));

    expect((new GenerateCacheJob())->getDescription())->toBe($description);
})->with([
    [0, 'Generating Blitz cache'],
    [1, 'Generating Blitz cache'],
    [2, 'Generating Blitz cache'],
    [3, 'Generating Blitz cache (batch 1 of 2)'],
]);

test('Generation continuation descriptions refresh the remaining workload', function() {
    $descriptions = [];
    $this->queue->on(Queue::EVENT_AFTER_PUSH, function($event) use (&$descriptions) {
        $descriptions[] = (new Query())->select('description')->from($this->queue->tableName)->where(['id' => $event->id])->scalar();
    });
    $this->generator->generateUris($this->uris);
    ($this->drain)();

    expect($descriptions)->toBe([
        'Generating Blitz cache (batch 1 of 2)',
        'Generating Blitz cache (batch 2 of 2)',
    ]);
});

test('Single-batch purge and deployment jobs retain their normal descriptions', function(string $driverId, string $driverMethod, string $description) {
    $job = new DriverJob(['driverId' => $driverId, 'driverMethod' => $driverMethod, 'description' => $description]);
    [$a, $b] = $this->uris;
    DriverQueueHelper::add($driverId, $driverMethod, [$a, $b, $a]);
    expect($job->getDescription())->toBe($description);
})->with([
    ['cachePurger', 'purgeUrisWithProgress', 'Purging Blitz cache'],
    ['deployer', 'deployUrisWithProgress', 'Deploying files'],
]);

test('Custom driver methods retain their configured descriptions', function() {
    $job = new DriverJob(['driverId' => 'deployer', 'driverMethod' => 'customMethod', 'description' => 'Custom operation']);
    expect($job->getDescription())->toBe('Custom operation');
});

test('Custom components and methods share the queue without consuming each other’s work', function() {
    $driver = new class() extends \yii\base\Component {
        public array $calls = [];

        public function first(array $siteUris, ?callable $setProgressHandler = null): void
        {
            $this->calls[] = ['first', array_map(fn($siteUri) => $siteUri->uri, $siteUris)];
        }

        public function second(array $siteUris, ?callable $setProgressHandler = null): void
        {
            $this->calls[] = ['second', array_map(fn($siteUri) => $siteUri->uri, $siteUris)];
        }
    };
    Blitz::$plugin->set('customDriver', $driver);
    Blitz::$plugin->set('anotherCustomDriver', $driver);
    try {
        [$a] = $this->uris;
        BaseDriverHelper::addDriverJob([$a, $a], 'customDriver', 'first', 'Custom first');
        BaseDriverHelper::addDriverJob([$a], 'customDriver', 'first', 'Custom first');
        BaseDriverHelper::addDriverJob([$a], 'customDriver', 'second', 'Custom second');
        BaseDriverHelper::addDriverJob([$a], 'anotherCustomDriver', 'first', 'Another first');

        expect((int)DriverQueueRecord::find()->count())->toBe(3)
            ->and((int)QueueSiteUriRecord::find()->count())->toBe(1);
        ($this->drain)();
        expect($driver->calls)->toBe([
            ['first', ['work-a']],
            ['second', ['work-a']],
            ['first', ['work-a']],
        ])
            ->and((int)DriverQueueRecord::find()->count())->toBe(0)
            ->and((int)QueueSiteUriRecord::find()->count())->toBe(0);
    } finally {
        Blitz::$plugin->clear('customDriver');
        Blitz::$plugin->clear('anotherCustomDriver');
    }
});

test('Custom methods run independently of standard generation prerequisites', function() {
    [$a] = $this->uris;
    DriverQueueHelper::add(CacheGeneratorHelper::DRIVER_ID, 'generateUrisWithProgress', [$a]);
    DriverQueueHelper::add(DeployerHelper::DRIVER_ID, 'customMethod', [$a]);
    expect(DriverQueueHelper::process(DeployerHelper::DRIVER_ID, 'customMethod', fn() => null))->toBe(1)
        ->and(DriverQueueHelper::getPendingCount(CacheGeneratorHelper::DRIVER_ID, 'generateUrisWithProgress'))->toBe(1);
});

test('Releasing a custom driver cancels all its methods while retaining other drivers', function() {
    [$a] = $this->uris;
    BaseDriverHelper::addDriverJob([$a], 'customDriver', 'first', 'Custom first');
    BaseDriverHelper::addDriverJob([$a], 'customDriver', 'second', 'Custom second');
    $this->generator->generateUris([$a]);
    BaseDriverHelper::releaseDriverJobs('customDriver');

    expect((int)DriverQueueRecord::find()->count())->toBe(1)
        ->and(DriverQueueHelper::getPendingCount(CacheGeneratorHelper::DRIVER_ID, 'generateUrisWithProgress'))->toBe(1);
    ($this->drain)();
    expect($this->generator->batches)->toBe([['work-a']]);
});

test('Releasing shared work leaves its jobs available for newer requests', function(string $driverId, string $method) {
    [$a, $b] = $this->uris;
    $driver = Blitz::$plugin->get($driverId);
    $driver->$method([$a]);
    $jobId = (new Query())->select('id')->from($this->queue->tableName)->where(['channel' => $this->queue->channel])->scalar();
    BaseDriverHelper::releaseDriverJobs($driverId);

    expect(DriverQueueHelper::getPendingCount($driverId, $method . 'WithProgress'))->toBe(0)
        ->and((new Query())->from($this->queue->tableName)->where(['id' => $jobId])->exists())->toBeTrue();

    $driver->$method([$b]);
    ($this->drain)();
    expect($driver->batches)->toBe([['work-b']]);
})->with([
    ['cacheGenerator', 'generateUris'],
    ['deployer', 'deployUris'],
    ['cachePurger', 'purgeUris'],
]);

test('Releasing a driver ignores its name in another job’s description', function() {
    BaseDriverHelper::addDriverJob([$this->uris[0]], 'cachePurger', 'purgeUrisWithProgress', 'deployer');
    BaseDriverHelper::releaseDriverJobs('deployer');
    ($this->drain)();

    expect($this->purger->batches)->toBe([['work-a']]);
});

test('Releasing site purges preserves other channels, running jobs and misleading descriptions', function() {
    $siteId = $this->uris[0]->siteId;
    $waitingId = $this->queue->push(new PurgeSiteJob(['siteId' => $siteId]));
    $runningId = $this->queue->push(new PurgeSiteJob(['siteId' => $siteId]));
    Db::update($this->queue->tableName, ['dateReserved' => Db::prepareDateForDb('now'), 'timeUpdated' => time()], ['id' => $runningId]);
    $otherQueue = new Queue(['channel' => 'blitz-other-' . bin2hex(random_bytes(6))]);
    $otherId = $otherQueue->push(new PurgeSiteJob(['siteId' => $siteId]));
    $unrelatedId = $this->queue->push(new GenerateCacheJob(['description' => PurgeSiteJob::class]));

    CachePurgerHelper::releasePurgerJobs();

    $exists = fn($id) => (new Query())->from($this->queue->tableName)->where(['id' => $id])->exists();
    expect($exists($waitingId))->toBeFalse()
        ->and($exists($runningId))->toBeTrue()
        ->and($exists($otherId))->toBeTrue()
        ->and($exists($unrelatedId))->toBeTrue();
});

test('Site purge cancellation uses the configured queue API and tolerates concurrent workers', function() {
    $db = Mockery::mock(Connection::class . '[createCommand]', [['dsn' => 'mysql:host=unused;dbname=unused']]);
    $db->shouldNotReceive('createCommand');
    $queue = Mockery::mock(Queue::class . '[getJobInfo,getJobDetails,release]', [[
        'db' => $db,
        'channel' => 'external',
        'tableName' => '{{%external_queue}}',
    ]]);
    $job = new PurgeSiteJob(['siteId' => $this->uris[0]->siteId]);
    $queue->shouldReceive('getJobInfo')->once()->andReturn([
        ['id' => 'gone', 'status' => Queue::STATUS_WAITING],
        ['id' => 'running', 'status' => Queue::STATUS_RESERVED],
        ['id' => 'claimed', 'status' => Queue::STATUS_WAITING],
        ['id' => 'waiting', 'status' => Queue::STATUS_WAITING],
    ]);
    $queue->shouldReceive('getJobDetails')->with('gone')->once()->andThrow(new \yii\base\InvalidArgumentException());
    $queue->shouldNotReceive('getJobDetails')->with('running');
    $queue->shouldReceive('getJobDetails')->with('claimed')->once()->andReturn(['status' => Queue::STATUS_RESERVED, 'job' => $job]);
    $queue->shouldReceive('getJobDetails')->with('waiting')->once()->andReturn(['status' => Queue::STATUS_WAITING, 'job' => $job]);
    $queue->shouldReceive('release')->with('waiting')->once();
    Blitz::$plugin->queue = $queue;

    BaseDriverHelper::releaseDriverJobs(CachePurgerHelper::DRIVER_ID);
});

test('Queue payloads contain no URL lists and stale jobs perform no driver calls', function() {
    foreach (['generateUris' => $this->generator, 'deployUris' => $this->deployer, 'purgeUris' => $this->purger] as $method => $driver) {
        $driver->$method([$this->uris[0], $this->uris[0]]);
        $driver->$method([$this->uris[0]]);
    }
    $payloads = (new Query())->select('job')->from($this->queue->tableName)->where(['channel' => $this->queue->channel])->column();
    expect($payloads)->toHaveCount(6);
    foreach ($payloads as $payload) {
        expect((string)$payload)->not->toContain('work-a');
    }
    ($this->drain)();
    foreach ([$this->generator, $this->deployer, $this->purger] as $driver) {
        expect($driver->batches)->toBe([['work-a']]);
    }
});

test('Releasing generation jobs cancels their pending work and unused identities', function() {
    $this->generator->generateUris($this->uris);
    CacheGeneratorHelper::releaseGeneratorJobs();
    expect(DriverQueueHelper::getPendingPriority(CacheGeneratorHelper::DRIVER_ID, 'generateUrisWithProgress'))->toBeNull()
        ->and((int)QueueSiteUriRecord::find()->count())->toBe(0);
    ($this->drain)();
    expect($this->generator->batches)->toBe([]);
});
