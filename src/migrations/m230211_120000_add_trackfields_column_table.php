<?php

namespace putyourlightson\blitz\migrations;

use craft\db\Migration;
use craft\records\Element;
use craft\records\Field;
use putyourlightson\blitz\records\CacheRecord;
use putyourlightson\blitz\records\ElementCacheRecord;
use putyourlightson\blitz\records\ElementFieldCacheRecord;

class m230211_120000_add_trackfields_column_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        if (!$this->db->columnExists(ElementCacheRecord::tableName(), 'trackAllFields')) {
            $this->addColumn(
                ElementCacheRecord::tableName(),
                'trackAllFields',
                $this->boolean()->notNull()->defaultValue(1)->after('elementId'),
            );
        }

        if (!$this->db->tableExists(ElementFieldCacheRecord::tableName())) {
            $this->createTable(ElementFieldCacheRecord::tableName(), [
                'cacheId' => $this->integer()->notNull(),
                'elementId' => $this->integer()->notNull(),
                'fieldId' => $this->integer()->notNull(),
                'PRIMARY KEY([[cacheId]], [[elementId]], [[fieldId]])',
            ]);

            $this->addForeignKey(null, ElementFieldCacheRecord::tableName(), 'cacheId', CacheRecord::tableName(), 'id', 'CASCADE', 'CASCADE');
            $this->addForeignKey(null, ElementFieldCacheRecord::tableName(), 'elementId', Element::tableName(), 'id', 'CASCADE', 'CASCADE');
            $this->addForeignKey(null, ElementFieldCacheRecord::tableName(), 'fieldId', Field::tableName(), 'id', 'CASCADE', 'CASCADE');
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
