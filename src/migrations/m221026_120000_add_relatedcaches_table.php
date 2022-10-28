<?php

namespace putyourlightson\blitz\migrations;

use craft\db\Migration;
use putyourlightson\blitz\records\CacheRecord;
use putyourlightson\blitz\records\RelatedCacheRecord;
use putyourlightson\blitzhints\migrations\Install as HintsInstall;

class m221026_120000_add_relatedcaches_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        if (!$this->db->tableExists(RelatedCacheRecord::tableName())) {
            $this->createTable(RelatedCacheRecord::tableName(), [
                'cacheId' => $this->integer()->notNull(),
                'relatedCacheId' => $this->integer()->notNull(),
                'PRIMARY KEY([[cacheId]], [[relatedCacheId]])',
            ]);

            $this->createIndex(null, RelatedCacheRecord::tableName(), 'cacheId');

            $this->addForeignKey(null, RelatedCacheRecord::tableName(), 'cacheId', CacheRecord::tableName(), 'id', 'CASCADE', 'CASCADE');
            $this->addForeignKey(null, RelatedCacheRecord::tableName(), 'relatedCacheId', CacheRecord::tableName(), 'id', 'CASCADE', 'CASCADE');
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
