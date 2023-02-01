<?php

namespace putyourlightson\blitz\migrations;

use craft\db\Migration;
use putyourlightson\blitz\records\CacheRecord;
use putyourlightson\blitz\records\IncludeRecord;
use putyourlightson\blitz\records\SsiIncludeCacheRecord;

class m221026_120000_add_include_tables extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        if (!$this->db->tableExists(IncludeRecord::tableName())) {
            $this->createTable(IncludeRecord::tableName(), [
                'id' => $this->primaryKey(),
                'index' => $this->bigInteger()->notNull(),
                'siteId' => $this->integer()->notNull(),
                'template' => $this->string()->notNull(),
                'params' => $this->text()->notNull(),
            ]);

            $this->createIndex(null, IncludeRecord::tableName(), 'index', true);
        }

        if (!$this->db->tableExists(SsiIncludeCacheRecord::tableName())) {
            $this->createTable(SsiIncludeCacheRecord::tableName(), [
                'cacheId' => $this->integer()->notNull(),
                'includeId' => $this->integer()->notNull(),
                'PRIMARY KEY([[cacheId]], [[includeId]])',
            ]);

            $this->addForeignKey(null, SsiIncludeCacheRecord::tableName(), 'cacheId', CacheRecord::tableName(), 'id', 'CASCADE', 'CASCADE');
            $this->addForeignKey(null, SsiIncludeCacheRecord::tableName(), 'includeId', IncludeRecord::tableName(), 'id', 'CASCADE', 'CASCADE');
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo self::class . " cannot be reverted.\n";

        return true;
    }
}
