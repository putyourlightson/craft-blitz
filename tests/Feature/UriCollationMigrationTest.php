<?php

/**
 * Tests URI collation migration driver checks and repeated execution.
 */

use craft\db\Command;
use craft\db\Connection;
use craft\db\TableSchema;
use putyourlightson\blitz\migrations\m261010_120000_make_uri_comparisons_sensitive;
use yii\db\ColumnSchema;

test('URI migration skips an already configured collation', function() {
    (new m261010_120000_make_uri_comparisons_sensitive(['compact' => true]))->safeUp();
    $migration = new class(['compact' => true]) extends m261010_120000_make_uri_comparisons_sensitive {
        public function alterColumn($table, $column, $type): void
        {
            throw new RuntimeException('An already configured URI column must not be altered.');
        }
    };

    expect($migration->safeUp())->toBeTrue();
});

test('URI migration reads MySQL charset directly without Craft column collation metadata', function() {
    $db = Mockery::mock(Connection::class);
    $db->shouldReceive('getSchema')->andReturn(Craft::$app->getDb()->getSchema());
    $db->shouldReceive('getTableSchema')->once()->andReturn(new TableSchema([
        'name' => 'blitz_caches',
        'columns' => ['uri' => new ColumnSchema(['size' => 2048])],
    ]));
    $db->shouldReceive('getIsMysql')->once()->andReturn(true);
    $command = Mockery::mock(Command::class);
    $command->shouldReceive('queryOne')->once()->andReturn([
        'charset' => 'utf8mb3',
        'collation' => 'utf8mb3_general_ci',
    ]);
    $db->shouldReceive('createCommand')->once()->andReturn($command);

    $migration = new class(['db' => $db, 'compact' => true]) extends m261010_120000_make_uri_comparisons_sensitive {
        public ?string $columnType = null;

        public function alterColumn($table, $column, $type): void
        {
            $this->columnType = (string)$type;
        }
    };
    expect($migration->safeUp())->toBeTrue()
        ->and($migration->columnType)->toContain('string(2048)', 'COLLATE utf8mb3_bin', 'NOT NULL');
});

test('URI migration resolves PostgreSQL table names before binding them as regclass', function(string $fullName, string $quotedName) {
    $db = Mockery::mock(Connection::class);
    $db->shouldReceive('getSchema')->andReturn(Craft::$app->getDb()->getSchema());
    $db->shouldReceive('getTableSchema')->once()->andReturn(new TableSchema([
        'fullName' => $fullName,
        'columns' => ['uri' => new ColumnSchema(['size' => 2048])],
    ]));
    $db->shouldReceive('getIsMysql')->once()->andReturn(false);
    $db->shouldReceive('getIsPgsql')->once()->andReturn(true);
    $schema = new yii\db\pgsql\Schema();
    $db->shouldReceive('quoteTableName')->andReturnUsing($schema->quoteTableName(...));
    $command = Mockery::mock(Command::class);
    $command->shouldReceive('queryScalar')->once()->andReturn('default');
    $db->shouldReceive('createCommand')->once()->with(
        Mockery::type('string'),
        [':table' => $quotedName, ':column' => 'uri'],
    )->andReturn($command);

    $migration = new class(['db' => $db, 'compact' => true]) extends m261010_120000_make_uri_comparisons_sensitive {
        public ?string $columnType = null;

        public function alterColumn($table, $column, $type): void
        {
            $this->columnType = (string)$type;
        }
    };
    expect($migration->safeUp())->toBeTrue()
        ->and($migration->columnType)->toContain('string(2048)', 'COLLATE "C"', 'NOT NULL');
})->with([
    'no prefix' => ['blitz_caches', '"blitz_caches"'],
    'table prefix' => ['prefix_blitz_caches', '"prefix_blitz_caches"'],
    'custom schema and table prefix' => ['custom.prefix_blitz_caches', '"custom"."prefix_blitz_caches"'],
]);

test('URI migration skips unsupported database drivers', function() {
    $db = Mockery::mock(Connection::class);
    $db->shouldReceive('getSchema')->andReturn(Craft::$app->getDb()->getSchema());
    $db->shouldReceive('getTableSchema')->once()->andReturn(new TableSchema([
        'columns' => ['uri' => new ColumnSchema(['size' => 2048])],
    ]));
    $db->shouldReceive('getIsMysql')->once()->andReturn(false);
    $db->shouldReceive('getIsPgsql')->once()->andReturn(false);

    $migration = new m261010_120000_make_uri_comparisons_sensitive(['db' => $db, 'compact' => true]);
    expect($migration->safeUp())->toBeTrue();
});
