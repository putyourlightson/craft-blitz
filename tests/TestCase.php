<?php

namespace putyourlightson\blitz\tests;

use Craft;
use markhuot\craftpest\test\TestCase as CraftTestCase;
use putyourlightson\blitz\services\CacheRequestService;
use yii\base\Event;

// Use the migrated installation without applying unrelated project config or clearing its queue.
abstract class TestCase extends CraftTestCase
{
    public function createApplication()
    {
        if ($this->needsRequireStatements()) {
            $handler = function($event) {
                $event->isValid = false;
            };
            Event::on(CacheRequestService::class, CacheRequestService::EVENT_IS_CACHEABLE_REQUEST, $handler);
            try {
                $this->requireCraft();
            } finally {
                Event::off(CacheRequestService::class, CacheRequestService::EVENT_IS_CACHEABLE_REQUEST, $handler);
            }
        }

        return Craft::$app;
    }
}
