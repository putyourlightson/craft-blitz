<?php

namespace putyourlightson\blitz\migrations;

use Craft;
use craft\db\Migration;
use craft\records\Element;
use putyourlightson\blitz\records\CacheTagRecord;
use putyourlightson\blitz\records\ElementCacheRecord;
use putyourlightson\blitz\records\ElementExpiryDateRecord;
use putyourlightson\blitz\records\ElementQueryCacheRecord;
use putyourlightson\blitz\records\ElementQueryRecord;
use putyourlightson\blitz\records\ElementQuerySourceRecord;

class m200721_120000_add_primary_keys extends Migration
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        if ($this->db->tableExists(ElementCacheRecord::tableName())) {
            $this->addPrimaryKey(null, ElementCacheRecord::tableName(), ['cacheId', 'elementId']);
        }

        if ($this->db->tableExists(ElementExpiryDateRecord::tableName())) {
            // Drop existing indexes first to avoid duplicate error (https://github.com/putyourlightson/craft-blitz/issues/240)
            $this->dropForeignKey(
                $this->getDb()->getForeignKeyName(ElementExpiryDateRecord::tableName(), 'elementId'),
                ElementExpiryDateRecord::tableName()
            );
            $this->dropIndex(
                $this->getDb()->getIndexName(ElementExpiryDateRecord::tableName(), 'elementId', true),
                ElementExpiryDateRecord::tableName()
            );

            $this->addPrimaryKey(null, ElementExpiryDateRecord::tableName(), ['elementId']);
            $this->addForeignKey(null, ElementExpiryDateRecord::tableName(), 'elementId', Element::tableName(), 'id', 'CASCADE', 'CASCADE');
        }

        if ($this->db->tableExists(ElementQueryCacheRecord::tableName())) {
            $this->addPrimaryKey(null, ElementQueryCacheRecord::tableName(), ['cacheId', 'queryId']);
        }

        if ($this->db->tableExists(ElementQuerySourceRecord::tableName())) {
            $this->addColumn(ElementQuerySourceRecord::tableName(), 'id', $this->primaryKey()->first());
        }

        if ($this->db->tableExists(CacheTagRecord::tableName())) {
            $this->addPrimaryKey(null, CacheTagRecord::tableName(), ['cacheId', 'tag']);
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo self::class." cannot be reverted.\n";

        return false;
    }
}
