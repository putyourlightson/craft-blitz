<?php

use craft\helpers\Db;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\helpers\SiteUriHelper;
use putyourlightson\blitz\migrations\m261118_130000_add_cache_uri_hashes;
use putyourlightson\blitz\records\CacheRecord;
use putyourlightson\blitz\records\CacheTagRecord;
use putyourlightson\blitz\services\FlushCacheService;

uses()->group('all');

test('Cache URI hash migration flushes metadata before requiring hashes', function(bool $partiallyApplied) {
    $db = Craft::$app->getDb();
    $connection = Craft::createObject([
        'class' => $db::class,
        'dsn' => $db->dsn,
        'username' => $db->username,
        'password' => $db->password,
        'charset' => $db->charset,
        'schemaMap' => $db->schemaMap,
        'commandMap' => $db->commandMap,
        'tablePrefix' => 'hash_' . bin2hex(random_bytes(4)) . '_',
    ]);
    Craft::$app->set('db', $connection);
    $migration = new m261118_130000_add_cache_uri_hashes(['db' => $connection, 'compact' => true]);
    $cacheTable = CacheRecord::tableName();
    $originalFlushCache = Blitz::$plugin->get('flushCache');
    $flushCache = Mockery::mock(FlushCacheService::class . '[runGarbageCollection]');
    $flushCache->shouldReceive('runGarbageCollection')->once()->with(false);
    Blitz::$plugin->set('flushCache', $flushCache);

    try {
        $migration->createTable($cacheTable, [
            'id' => $migration->primaryKey(),
            'siteId' => $migration->integer()->notNull(),
            'uri' => $migration->string(2048)->notNull(),
            'expiryDate' => $migration->dateTime(),
        ]);
        $migration->createTable(CacheTagRecord::tableName(), [
            'cacheId' => $migration->integer()->notNull(),
            'tag' => $migration->string()->notNull(),
            'PRIMARY KEY ([[cacheId]], [[tag]])',
        ]);
        $migration->addForeignKey(null, CacheTagRecord::tableName(), 'cacheId', $cacheTable, 'id', 'CASCADE', 'CASCADE');
        $uri = str_repeat('a', 1000);
        $rows = [
            [1, 1, $uri, '2025-01-01 00:00:00'],
            [2, 1, $uri, '2026-01-01 00:00:00'],
            [8, 1, $uri . 'b', null],
            [3, 1, $uri . 'b', null],
            [4, 1, $uri . 'B', null],
            [5, 2, $uri, null],
            [6, 1, 'Übergröße?value=A', null],
            [7, 12, '', null],
        ];
        $connection->createCommand()->batchInsert($cacheTable, ['id', 'siteId', 'uri', 'expiryDate'], $rows)->execute();
        $connection->createCommand()->batchInsert(CacheTagRecord::tableName(), ['cacheId', 'tag'], [
            [1, 'older'],
            [2, 'newer'],
        ])->execute();
        if ($partiallyApplied) {
            $migration->addColumn($cacheTable, 'uriHash', $migration->char(32));
            $connection->createCommand()->update($cacheTable, [
                'uriHash' => SiteUriHelper::getSiteUriHash(1, $uri),
            ], ['id' => 1])->execute();
        }
        expect($migration->safeUp())->toBeTrue();
        expect(CacheRecord::find()->count())->toEqual(0)
            ->and(CacheTagRecord::find()->count())->toEqual(0)
            ->and(Db::findIndex($cacheTable, ['uriHash'], true, $connection))->not->toBeNull()
            ->and($connection->getTableSchema($cacheTable, true)->columns['uriHash']->size)->toBe(32)
            ->and($connection->getTableSchema($cacheTable)->columns['uriHash']->allowNull)->toBeFalse();
        $connection->createCommand()->insert($cacheTable, [
            'siteId' => 1,
            'uri' => $uri,
            'uriHash' => SiteUriHelper::getSiteUriHash(1, $uri),
        ])->execute();
        expect($migration->safeUp())->toBeTrue()
            ->and(CacheRecord::find()->count())->toEqual(1);
        expect(fn() => $connection->createCommand()->insert($cacheTable, [
            'siteId' => 1,
            'uri' => $uri,
            'uriHash' => SiteUriHelper::getSiteUriHash(1, $uri),
        ])->execute())->toThrow(\yii\db\IntegrityException::class);
        expect($migration->safeDown())->toBeTrue()
            ->and($connection->columnExists($cacheTable, 'uriHash', true))->toBeFalse();
    } finally {
        try {
            $migration->dropTableIfExists(CacheTagRecord::tableName());
            $migration->dropTableIfExists($cacheTable);
        } finally {
            Blitz::$plugin->set('flushCache', $originalFlushCache);
            Craft::$app->set('db', $db);
            $connection->close();
        }
    }
})->with([
    'new migration' => false,
    'partially applied migration' => true,
]);
