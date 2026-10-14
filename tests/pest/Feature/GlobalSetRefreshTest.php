<?php

/**
 * Tests full cache refreshes when global sets are saved.
 */

use craft\elements\GlobalSet;
use craft\helpers\StringHelper;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\jobs\GenerateCacheJob;
use putyourlightson\blitz\models\SettingsModel;
use putyourlightson\blitz\services\RefreshCacheService;
use yii\queue\PushEvent;
use yii\queue\Queue;

test('Saving a global set queues one generation run only when automatic refresh is enabled', function(bool $enabled) {
    $settings = Blitz::$plugin->settings;
    $originalRefreshMode = $settings->refreshMode;
    $originalRefreshGlobals = $settings->refreshCacheAutomaticallyForGlobals;
    $settings->refreshMode = SettingsModel::REFRESH_MODE_EXPIRE_AND_GENERATE;
    $settings->refreshCacheAutomaticallyForGlobals = $enabled;

    $globalSet = Craft::$app->getGlobals()->getSetByHandle('test');
    expect($globalSet)->toBeInstanceOf(GlobalSet::class);
    $value = StringHelper::randomString();
    $globalSet->setFieldValue('plainText', $value);

    $refreshCount = 0;
    $savedValues = [];
    $jobIds = [];
    $refreshHandler = function() use (&$refreshCount, &$savedValues, $globalSet) {
        $refreshCount++;
        $savedValues[] = GlobalSet::find()->id($globalSet->id)->siteId($globalSet->siteId)->one()->getFieldValue('plainText');
    };
    $pushHandler = function(PushEvent $event) use (&$jobIds) {
        if ($event->job instanceof GenerateCacheJob) {
            $jobIds[] = $event->id;
        }
    };
    $refreshCache = Blitz::$plugin->refreshCache;
    $queue = Blitz::$plugin->queue;
    $refreshCache->on(RefreshCacheService::EVENT_BEFORE_REFRESH_ALL_CACHE, $refreshHandler);
    $queue->on(Queue::EVENT_AFTER_PUSH, $pushHandler);

    try {
        expect($refreshCache->isRefreshableElement($globalSet))->toBeFalse()
            ->and($refreshCount)->toBe(0)
            ->and($jobIds)->toBeEmpty();

        expect(Craft::$app->getElements()->saveElement($globalSet))->toBeTrue()
            ->and($refreshCount)->toBe($enabled ? 1 : 0)
            ->and($jobIds)->toHaveCount($enabled ? 1 : 0)
            ->and($savedValues)->toBe($enabled ? [$value] : []);
    } finally {
        $refreshCache->off(RefreshCacheService::EVENT_BEFORE_REFRESH_ALL_CACHE, $refreshHandler);
        $queue->off(Queue::EVENT_AFTER_PUSH, $pushHandler);
        foreach ($jobIds as $jobId) {
            $queue->release($jobId);
        }
        $settings->refreshMode = $originalRefreshMode;
        $settings->refreshCacheAutomaticallyForGlobals = $originalRefreshGlobals;
    }
})->with([true, false]);

test('Propagating global sets do not refresh the cache', function() {
    $globalSet = new GlobalSet(['propagating' => true]);
    $refreshCache = Mockery::mock(RefreshCacheService::class . '[refreshAll]');
    $refreshCache->shouldNotReceive('refreshAll');

    $refreshCache->addElement($globalSet);

    expect($refreshCache->isRefreshableElement($globalSet))->toBeFalse();
});
