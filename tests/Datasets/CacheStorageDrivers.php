<?php

use craft\helpers\App;
use putyourlightson\blitz\drivers\storage\FileStorage;
use putyourlightson\blitz\drivers\storage\YiiCacheStorage;
use yii\redis\Cache;
use yii\redis\Connection;

dataset('cache storage drivers', [
    'FileStorage' => FileStorage::class,
    'YiiCacheStorage' => function() {
        // Set cache component to Craft’s default
        Craft::$app->set('cache', App::cacheConfig());

        return YiiCacheStorage::class;
    },
    'RedisStorage' => function() {
        // Set cache component to Redis
        Craft::$app->set('redis', [
            'class' => Connection::class,
            'hostname' => 'redis',
            'port' => 6379,
        ]);
        Craft::$app->set('cache', [
            'class' => Cache::class,
            'defaultDuration' => 86400,
            'keyPrefix' => 'CraftCMS',
        ]);

        return YiiCacheStorage::class;
    },
]);
