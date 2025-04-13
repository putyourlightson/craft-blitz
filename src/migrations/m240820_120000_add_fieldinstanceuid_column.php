<?php

namespace putyourlightson\blitz\migrations;

use craft\db\Migration;
use craft\records\Element;
use putyourlightson\blitz\records\CacheRecord;
use putyourlightson\blitz\records\ElementFieldCacheRecord;
use putyourlightson\blitz\records\ElementQueryFieldRecord;
use putyourlightson\blitz\records\ElementQueryRecord;

class m240820_120000_add_fieldinstanceuid_column extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        if ($this->db->tableExists(ElementFieldCacheRecord::tableName())) {
            $this->dropTable(ElementFieldCacheRecord::tableName());
            $this->createTable(ElementFieldCacheRecord::tableName(), [
                'cacheId' => $this->integer()->notNull(),
                'elementId' => $this->integer()->notNull(),
                'fieldInstanceUid' => $this->uid(),
                'PRIMARY KEY([[cacheId]], [[elementId]], [[fieldInstanceUid]])',
            ]);
            $this->addForeignKey(null, ElementFieldCacheRecord::tableName(), 'cacheId', CacheRecord::tableName(), 'id', 'CASCADE', 'CASCADE');
            $this->addForeignKey(null, ElementFieldCacheRecord::tableName(), 'elementId', Element::tableName(), 'id', 'CASCADE', 'CASCADE');
        }

        if ($this->db->tableExists(ElementQueryFieldRecord::tableName())) {
            $this->dropTable(ElementQueryFieldRecord::tableName());
            $this->createTable(ElementQueryFieldRecord::tableName(), [
                'queryId' => $this->integer()->notNull(),
                'fieldInstanceUid' => $this->uid(),
                'PRIMARY KEY([[queryId]], [[fieldInstanceUid]])',
            ]);
            $this->addForeignKey(null, ElementQueryFieldRecord::tableName(), 'queryId', ElementQueryRecord::tableName(), 'id', 'CASCADE', 'CASCADE');
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
