<?php

use craft\db\Table;
use craft\elements\Entry;
use craft\helpers\Db;
use craft\helpers\FileHelper;
use craft\queue\jobs\UpdateElementSlugsAndUris;
use craft\queue\Queue;
use craft\services\Elements;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\drivers\purgers\DummyPurger;
use putyourlightson\blitz\drivers\storage\FileStorage;
use putyourlightson\blitz\jobs\DriverJob;
use putyourlightson\blitz\jobs\GenerateCacheJob;
use putyourlightson\blitz\jobs\RefreshCacheJob;
use putyourlightson\blitz\models\SettingsModel;
use putyourlightson\blitz\models\SiteUriModel;
use putyourlightson\blitz\tests\Support\DescendantUriDeployer;
use putyourlightson\blitz\tests\Support\DescendantUriGenerator;
use yii\base\Event;
use yii\web\NotFoundHttpException;

require_once __DIR__ . '/../Support/DescendantUriGenerator.php';
require_once __DIR__ . '/../Support/DescendantUriDeployer.php';

uses()->group('all');

beforeEach(function() {
    $this->transaction = Craft::$app->getDb()->beginTransaction();
    $this->settings = Blitz::$plugin->settings->getAttributes();
    $this->components = [];
    foreach (['cacheStorage', 'cacheGenerator', 'deployer', 'cachePurger'] as $id) {
        $this->components[$id] = Blitz::$plugin->get($id);
    }
    $this->appQueue = Craft::$app->getQueue();
    $this->blitzQueue = Blitz::$plugin->queue;
    $this->queue = new Queue(['channel' => 'blitz-descendant-' . bin2hex(random_bytes(6))]);
    Craft::$app->set('queue', $this->queue);
    Blitz::$plugin->queue = $this->queue;
    $this->folder = Craft::$app->getPath()->getTempPath() . '/blitz-descendant-' . bin2hex(random_bytes(6));
    Blitz::$plugin->set('cacheStorage', new FileStorage(['folderPath' => $this->folder . '/cache']));
    Blitz::$plugin->set('cacheGenerator', new DescendantUriGenerator());
    Blitz::$plugin->set('deployer', new DescendantUriDeployer(['folder' => $this->folder . '/deploy']));
    Blitz::$plugin->set('cachePurger', new DummyPurger());
    Blitz::$plugin->settings->cachingEnabled = true;
    Blitz::$plugin->settings->refreshMode = SettingsModel::REFRESH_MODE_CLEAR_AND_GENERATE;
    Blitz::$plugin->settings->refreshCacheWhenElementSavedUnchanged = false;
    Blitz::$plugin->settings->driverJobBatchSize = 1000;
    $this->section = Craft::$app->getEntries()->getSectionByHandle('structure');
    expect($this->section)->not->toBeNull();
    $this->template = '_blitz-descendant-' . bin2hex(random_bytes(6));
    $this->templatePath = Craft::$app->getPath()->getSiteTemplatesPath() . '/' . $this->template . '.twig';
    FileHelper::writeToFile($this->templatePath, '{{ entry.uri }}{% for page in craft.entries.sectionId(entry.sectionId).siteId(entry.siteId).all() %}<a href="{{ page.url }}">{{ page.uri }}</a>{% endfor %}');
    $this->siteSettings = [];
    foreach ($this->section->getSiteSettings() as $siteId => $settings) {
        $this->siteSettings[$siteId] = [$settings->uriFormat, $settings->template];
        $settings->uriFormat = '{parent.uri ?? "structure"}/{slug}';
        $settings->template = $this->template;
    }

    $this->entries = [];
    $parent = null;
    foreach (['parent', 'child', 'grandchild'] as $name) {
        $entry = new Entry([
            'sectionId' => $this->section->id,
            'typeId' => $this->section->getEntryTypes()[0]->id,
            'siteId' => Craft::$app->getSites()->getPrimarySite()->id,
            'title' => $name . '-' . bin2hex(random_bytes(4)),
        ]);
        $entry->setParent($parent);
        expect(Craft::$app->getElements()->saveElement($entry))->toBeTrue();
        $this->entries[] = $entry;
        $parent = $entry;
    }
    Db::delete($this->queue->tableName, ['channel' => $this->queue->channel]);
    Blitz::$plugin->refreshCache->reset();
    Blitz::$plugin->generateCache->reset();
});

afterEach(function() {
    Blitz::$plugin->refreshCache->reset();
    Blitz::$plugin->generateCache->reset();
    $this->transaction->rollBack();
    foreach ($this->section->getSiteSettings() as $siteId => $settings) {
        [$settings->uriFormat, $settings->template] = $this->siteSettings[$siteId];
    }
    foreach ($this->components as $id => $component) {
        Blitz::$plugin->set($id, $component);
    }
    Craft::$app->set('queue', $this->appQueue);
    Blitz::$plugin->queue = $this->blitzQueue;
    Blitz::$plugin->settings->setAttributes($this->settings, false);
    FileHelper::removeDirectory($this->folder);
    unlink($this->templatePath);
});

test('Descendant URI changes use one refresh and deploy, including retries', function(bool $alreadyUpdated, bool $missingVariant) {
    $ids = array_map(fn($entry) => $entry->id, $this->entries);
    if ($missingVariant) {
        Db::delete(Table::ELEMENTS_SITES, ['elementId' => $ids[2], 'siteId' => $this->entries[0]->siteId]);
    }
    $variants = Entry::find()->id($ids)->siteId('*')->unique(false)->all();
    $oldUris = array_map(fn($entry) => new SiteUriModel(['siteId' => $entry->siteId, 'uri' => $entry->uri]), $variants);
    Blitz::$plugin->cacheGenerator->generateUris($oldUris, queue: false);
    Blitz::$plugin->deployer->deployUris($oldUris, queue: false);
    foreach ($oldUris as $siteUri) {
        expect(is_file($this->folder . '/deploy/' . $siteUri->siteId . '/' . $siteUri->uri . '/index.html'))->toBeTrue();
    }

    $parent = $this->entries[0];
    $parent->slug .= '-renamed';
    expect(Craft::$app->getElements()->saveElement($parent))->toBeTrue();
    if ($missingVariant) {
        foreach (Entry::find()->id($parent->id)->siteId('*')->unique(false)->all() as $variant) {
            if ($variant->siteId !== $parent->siteId) {
                $variant->slug .= '-renamed';
                expect(Craft::$app->getElements()->saveElement($variant))->toBeTrue();
            }
        }
    }
    $data = Blitz::$plugin->refreshCache->refreshData->data;

    // Simulate a previous attempt that updated URIs but failed before generating any pages.
    if ($alreadyUpdated) {
        Craft::$app->getElements()->updateDescendantSlugsAndUris($parent, true, false);
        Blitz::$plugin->refreshCache->reset();
        Blitz::$plugin->refreshCache->refreshData->data = $data;
    }

    $jobs = [];
    $errors = [];
    $this->queue->on(Queue::EVENT_BEFORE_EXEC, function($event) use (&$jobs) {
        $jobs[] = $event->job;
    });
    $this->queue->on(Queue::EVENT_AFTER_ERROR, function($event) use (&$errors) {
        $errors[] = $event->error->getMessage();
    });
    Blitz::$plugin->refreshCache->refresh();
    $pending = Blitz::$plugin->refreshCache->refreshData;

    // Drain only this test’s queue and mimic the refresh callback at each request’s end.
    for ($i = 0; $i < 10 && $this->queue->executeJob(); $i++) {
        expect(implode("\n", $errors))->toBe('');
        Blitz::$plugin->refreshCache->refresh();
    }

    $blitzJobs = array_values(array_filter($jobs, fn($job) => $job instanceof RefreshCacheJob || $job instanceof GenerateCacheJob || $job instanceof DriverJob));
    expect(array_map(fn($job) => $job::class, $blitzJobs))->toBe([
        RefreshCacheJob::class,
        GenerateCacheJob::class,
        DriverJob::class,
    ])
        ->and($jobs[0])->toBeInstanceOf(RefreshCacheJob::class)
        ->and($jobs[1])->toBeInstanceOf(GenerateCacheJob::class)
        ->and($jobs[2])->toBeInstanceOf(DriverJob::class)
        ->and($jobs[3])->toBeInstanceOf(UpdateElementSlugsAndUris::class)
        ->and(Blitz::$plugin->refreshCache->refreshData)->toBe($pending)
        ->and($pending->isEmpty())->toBeTrue();

    $newUris = Entry::find()->id($ids)->siteId('*')->unique(false)->all();
    foreach ($newUris as $entry) {
        $path = $this->folder . '/deploy/' . $entry->siteId . '/' . $entry->uri . '/index.html';
        expect(is_file($path))->toBeTrue();
        $output = file_get_contents($path);
        $localizedParent = Entry::find()->id($parent->id)->siteId($entry->siteId)->one();
        expect($entry->uri)->toStartWith($localizedParent->uri)
            ->and($output)->toContain($entry->uri);
        foreach (Entry::find()->id($ids)->siteId($entry->siteId)->all() as $linked) {
            expect($output)->toContain($linked->uri);
        }
        if ($entry->siteId === $parent->siteId) {
            expect($entry->uri)->toContain($parent->slug)
                ->and(sendRequest($entry->uri)->statusCode)->toBe(200);
        }
    }
    foreach ($oldUris as $siteUri) {
        $stillExists = Entry::find()->siteId($siteUri->siteId)->uri($siteUri->uri)->exists();
        expect(is_file($this->folder . '/deploy/' . $siteUri->siteId . '/' . $siteUri->uri . '/index.html'))->toBe($stillExists);
        if ($siteUri->siteId === $parent->siteId) {
            expect(fn() => sendRequest($siteUri->uri))->toThrow(NotFoundHttpException::class);
        }
    }
})->with([
    'normal' => [false, false],
    'retry after URI updates' => [true, false],
    'descendant missing from the primary site' => [false, true],
]);

test('Descendant URI refresh skips entries without slug or URI changes', function() {
    $parent = $this->entries[0];
    $parent->title .= ' changed';
    expect(Craft::$app->getElements()->saveElement($parent))->toBeTrue();
    $data = Blitz::$plugin->refreshCache->refreshData;
    expect($data->getChangedAttributes(Entry::class, $parent->id))->not->toContain('slug', 'uri');
    $job = new RefreshCacheJob(['data' => $data->data]);
    Blitz::$plugin->refreshCache->reset();
    $updates = 0;
    $handler = function() use (&$updates) {
        $updates++;
    };
    Event::on(Elements::class, Elements::EVENT_BEFORE_UPDATE_SLUG_AND_URI, $handler);
    try {
        $job->execute($this->queue);
        expect($updates)->toBe(0)
            ->and(Blitz::$plugin->refreshCache->refreshData->isEmpty())->toBeTrue();
    } finally {
        Event::off(Elements::class, Elements::EVENT_BEFORE_UPDATE_SLUG_AND_URI, $handler);
    }
});

test('Descendant URI refresh restores pending data when an update fails', function() {
    $parent = $this->entries[0];
    $parent->slug .= '-renamed';
    expect(Craft::$app->getElements()->saveElement($parent))->toBeTrue();
    $job = new RefreshCacheJob(['data' => Blitz::$plugin->refreshCache->refreshData->data]);
    Blitz::$plugin->refreshCache->reset();
    $pending = Blitz::$plugin->refreshCache->refreshData;
    $pending->addCacheId(123);
    $handler = function() {
        throw new RuntimeException('URI update failed');
    };
    Event::on(Elements::class, Elements::EVENT_BEFORE_UPDATE_SLUG_AND_URI, $handler);
    try {
        expect(fn() => $job->execute($this->queue))->toThrow(RuntimeException::class, 'URI update failed')
            ->and(Blitz::$plugin->refreshCache->refreshData)->toBe($pending)
            ->and($pending->getCacheIds())->toBe([123]);
    } finally {
        Event::off(Elements::class, Elements::EVENT_BEFORE_UPDATE_SLUG_AND_URI, $handler);
    }
});
