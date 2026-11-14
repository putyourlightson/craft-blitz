<?php

/**
 * Tests MCP registration, permissions, input validation and queue submission.
 */

use craft\mcp\attributes\RequiresPermission;
use Mcp\Capability\Discovery\DocBlockParser;
use Mcp\Capability\Discovery\SchemaGenerator;
use Mcp\Capability\Registry\ToolReference;
use Mcp\Exception\ToolCallException;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\drivers\generators\BaseCacheGenerator;
use putyourlightson\blitz\mcp\CacheTools;
use putyourlightson\blitz\services\RefreshCacheService;

beforeEach(function() {
    if (!class_exists(\craft\services\Mcp::class)) {
        $this->markTestSkipped('Craft MCP support is not available.');
    }
});

test('MCP tools are registered outside control panel requests and have permission and scope metadata', function() {
    $mcp = new \craft\services\Mcp();
    $registry = $mcp->getCapabilityRegistry();
    expect($registry->types())->toContain(CacheTools::class);

    $definitions = $registry->definitions();
    $tools = array_filter($definitions->getTools(), fn(ToolReference $tool) => $tool->handler[0] === CacheTools::class);
    expect($tools)->toHaveCount(25);

    foreach ($tools as $name => $tool) {
        expect($name)->toStartWith('blitz.');
        $method = new ReflectionMethod(...$tool->handler);
        expect($method->getAttributes(RequiresPermission::class))->not->toBeEmpty();
        $readOnly = in_array($name, ['blitz.status', 'blitz.tags'], true);
        expect($tool->tool->annotations->readOnlyHint === true)->toBe($readOnly);
    }
});

test('MCP inputs describe non-empty string lists and positive site IDs', function() {
    $generator = new SchemaGenerator(new DocBlockParser());
    $schema = $generator->generate(new ReflectionMethod(CacheTools::class, 'refreshUrls'));
    expect($schema['properties']['urls']['items']['type'])->toBe('string')
        ->and($schema['properties']['urls']['minItems'])->toBe(1);

    $schema = $generator->generate(new ReflectionMethod(CacheTools::class, 'refreshSite'));
    expect($schema['properties']['siteId']['minimum'])->toBe(1);
});

test('MCP rejects empty or malformed lists before executing cache operations', function(string $method, array $values) {
    expect(fn() => (new CacheTools())->$method($values))->toThrow(ToolCallException::class);
})->with(['flushUrls', 'clearUrls', 'clearTagged', 'generateUrls', 'generateTagged', 'purgeUrls', 'purgeTagged', 'refreshUrls', 'refreshTagged'])
    ->with([[[]], [['']], [['   ']], [[null]], [[['nested']]], [[42]]]);

test('MCP rejects unknown sites before executing cache operations', function(string $method) {
    expect(fn() => (new CacheTools())->$method(-1))->toThrow(ToolCallException::class);
})->with(['flushSite', 'clearSite', 'generateSite', 'purgeSite', 'refreshSite']);

test('MCP does not generate or deploy when caching is disabled', function(string $method) {
    $enabled = Blitz::$plugin->settings->cachingEnabled;
    Blitz::$plugin->settings->cachingEnabled = false;

    try {
        expect(fn() => (new CacheTools())->$method())->toThrow(ToolCallException::class, 'Blitz caching is disabled.');
    } finally {
        Blitz::$plugin->settings->cachingEnabled = $enabled;
    }
})->with(['generate', 'deploy']);

test('MCP generation submits work to the driver without running the queue', function() {
    $plugin = Blitz::$plugin;
    $driver = $plugin->cacheGenerator;
    $enabled = $plugin->settings->cachingEnabled;
    $generator = Mockery::mock(BaseCacheGenerator::class);
    $generator->shouldReceive('generateAll')->once()->withNoArgs();
    $plugin->set('cacheGenerator', $generator);
    $plugin->settings->cachingEnabled = true;

    try {
        expect((new CacheTools())->generate()['success'])->toBeTrue();
    } finally {
        $plugin->set('cacheGenerator', $driver);
        $plugin->settings->cachingEnabled = $enabled;
    }
});

test('MCP tagged and expired refreshes submit pending work before returning', function(string $method, string $serviceMethod, array $arguments) {
    $plugin = Blitz::$plugin;
    $service = $plugin->refreshCache;
    $refresh = Mockery::mock(RefreshCacheService::class);
    $refresh->shouldReceive($serviceMethod)->once()->withArgs($arguments)->ordered();
    $refresh->shouldReceive('refresh')->once()->withNoArgs()->ordered();
    $plugin->set('refreshCache', $refresh);

    try {
        expect((new CacheTools())->$method(...$arguments)['success'])->toBeTrue();
    } finally {
        $plugin->set('refreshCache', $service);
    }
})->with([
    ['refreshTagged', 'refreshCacheTags', [['test-tag']]],
    ['refreshExpired', 'refreshExpiredCache', []],
    ['refreshExpiredElements', 'refreshExpiredElements', []],
]);


test('Blitz skips MCP registration when Craft MCP and the SDK are absent', function() {
    $code = <<<'CODE'
namespace craft\base {
    class Plugin {}
}
namespace {
    require $argv[1];
    require $argv[2];
    $class = new \ReflectionClass(\putyourlightson\blitz\Blitz::class);
    $plugin = $class->newInstanceWithoutConstructor();
    $class->getMethod('registerMcpTools')->invoke($plugin);
    echo 'compatible';
}
CODE;
    $process = new \Symfony\Component\Process\Process([
        PHP_BINARY,
        '-r',
        $code,
        dirname(__DIR__, 2) . '/src/Blitz.php',
        dirname(__DIR__, 2) . '/src/mcp/CacheTools.php',
    ]);
    $process->run();
    expect($process->getExitCode())->toBe(0)
        ->and($process->getOutput())->toBe('compatible');
});

test('MCP discovery only exposes operations allowed by the user’s Blitz permissions', function() {
    $originalIdentity = Craft::$app->getUser()->getIdentity();
    $user = Mockery::mock(\craft\elements\User::class)->makePartial();
    $user->shouldReceive('can')->andReturnUsing(fn(string $permission) => $permission === 'blitz:clear');
    Craft::$app->getUser()->setIdentity($user);

    try {
        $discovery = new \craft\mcp\CapabilityDiscovery(
            Craft::$app->getConfig()->getGeneral(),
            new \craft\mcp\CapabilityRegistry([CacheTools::class]),
        );
        $tools = $discovery->discoverStdio(getcwd(), [])->getTools();
        expect(array_keys($tools))->toEqualCanonicalizing([
            'blitz.clear', 'blitz.clear-site', 'blitz.clear-urls', 'blitz.clear-tagged',
        ]);
    } finally {
        Craft::$app->getUser()->setIdentity($originalIdentity);
    }
});

test('MCP expired refresh restores forced generation before the next tool call', function(bool $fails) {
    $plugin = Blitz::$plugin;
    $originalService = $plugin->refreshCache;
    $originalMode = $plugin->settings->refreshMode;
    $service = Mockery::mock(RefreshCacheService::class)->makePartial();
    $service->reset();
    $service->forceGenerate = false;
    $calls = [];
    $service->shouldReceive('refresh')->andReturnUsing(function() use ($service, &$calls, $fails) {
        $calls[] = $service->forceGenerate;
        $service->reset();
        if ($fails && count($calls) === 1) {
            throw new RuntimeException('Queue submission failed.');
        }
    });
    $plugin->set('refreshCache', $service);
    $plugin->settings->refreshMode = \putyourlightson\blitz\models\SettingsModel::REFRESH_MODE_EXPIRE;

    try {
        $tools = new CacheTools();
        if ($fails) {
            expect(fn() => $tools->refreshExpired())->toThrow(RuntimeException::class);
        } else {
            expect($tools->refreshExpired()['success'])->toBeTrue();
        }
        expect($service->forceGenerate)->toBeFalse();
        $tools->refreshTagged(['mcp-regression-tag']);
        expect($calls)->toBe([true, false]);
    } finally {
        $plugin->set('refreshCache', $originalService);
        $plugin->settings->refreshMode = $originalMode;
    }
})->with([false, true]);

test('MCP deployment includes configured custom URIs and preserves deployment events', function() {
    $plugin = Blitz::$plugin;
    $originalDriver = $plugin->deployer;
    $originalEnabled = $plugin->settings->cachingEnabled;
    $originalUris = $plugin->settings->customSiteUris;
    $siteId = Craft::$app->getSites()->getPrimarySite()->id;
    $customUri = 'mcp-custom-' . bin2hex(random_bytes(6));
    $plugin->settings->customSiteUris = [['siteId' => $siteId, 'uri' => $customUri]];
    $plugin->settings->cachingEnabled = true;
    $driver = Mockery::mock(\putyourlightson\blitz\drivers\deployers\BaseDeployer::class)->makePartial();
    $driver->isDummy = false;
    $driver->shouldReceive('deployUris')->once()->withArgs(function(array $siteUris, $progress, bool $queue) use ($siteId, $customUri) {
        $custom = array_filter($siteUris, fn($siteUri) => $siteUri->siteId === $siteId && $siteUri->uri === $customUri);
        return count($custom) === 1 && $queue && $progress === null;
    });
    $events = [];
    $driver->on($driver::EVENT_BEFORE_DEPLOY_ALL, function() use (&$events) {
        $events[] = 'before';
    });
    $driver->on($driver::EVENT_AFTER_DEPLOY_ALL, function() use (&$events) {
        $events[] = 'after';
    });
    $plugin->set('deployer', $driver);

    try {
        expect((new CacheTools())->deploy()['success'])->toBeTrue()
            ->and($events)->toBe(['before', 'after']);
    } finally {
        $plugin->set('deployer', $originalDriver);
        $plugin->settings->cachingEnabled = $originalEnabled;
        $plugin->settings->customSiteUris = $originalUris;
    }
});
