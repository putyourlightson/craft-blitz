<?php

/**
 * Tests queueing Cloudflare site purges without making API requests.
 */

use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\drivers\purgers\CloudflarePurger;
use putyourlightson\blitz\jobs\PurgeSiteJob;
use yii\queue\Queue;

test('Cloudflare site purges are queued at the driver job priority', function() {
    $originalQueue = Blitz::$plugin->queue;
    $queue = Mockery::mock(Queue::class);
    $queue->shouldReceive('priority')->once()->with(Blitz::$plugin->settings->driverJobPriority)->andReturnSelf();
    $queue->shouldReceive('delay')->once()->with(null)->andReturnSelf();
    $queue->shouldReceive('ttr')->once()->with(null)->andReturnSelf();
    $queue->shouldReceive('push')->once()->with(Mockery::on(function($job) {
        return $job instanceof PurgeSiteJob && $job->siteId === 1;
    }))->andReturn('123');
    Blitz::$plugin->queue = $queue;

    try {
        (new CloudflarePurger())->purgeSite(1);
    } finally {
        Blitz::$plugin->queue = $originalQueue;
    }
});

test('Queued site purges execute without queueing another job', function() {
    $originalPurger = Blitz::$plugin->cachePurger;
    $purger = Mockery::mock(CloudflarePurger::class);
    $purger->shouldReceive('purgeSite')->once()->with(1, null, false);
    Blitz::$plugin->set('cachePurger', $purger);

    try {
        (new PurgeSiteJob(['siteId' => 1]))->execute(Blitz::$plugin->queue);
    } finally {
        Blitz::$plugin->set('cachePurger', $originalPurger);
    }
});
