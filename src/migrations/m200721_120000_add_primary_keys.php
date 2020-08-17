<?php

namespace putyourlightson\blitz\migrations;

use craft\db\Migration;
use craft\records\Element;
use putyourlightson\blitz\records\CacheTagRecord;
use putyourlightson\blitz\records\ElementCacheRecord;
use putyourlightson\blitz\records\ElementExpiryDateRecord;
use putyourlightson\blitz\records\ElementQueryCacheRecord;
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
            // Drop existing indexes first to avoid duplicate error (https://github.com/putyourlightson/craft-blitz/issues/240)
            try {
                $this->dropIndex(
                    $this->getDb()->getIndexName(ElementCacheRecord::tableName(), ['cacheId', 'elementId'], true),
                    ElementCacheRecord::tableName()
                );
            }
            catch (\Exception $exception) {}

            $this->addPrimaryKey(null, ElementCacheRecord::tableName(), ['cacheId', 'elementId']);
        }

        if ($this->db->tableExists(ElementExpiryDateRecord::tableName())) {
            // Drop existing indexes first to avoid duplicate error (https://github.com/putyourlightson/craft-blitz/issues/240)
            try {
                $this->dropForeignKey(
                    $this->getDb()->getForeignKeyName(ElementExpiryDateRecord::tableName(), 'elementId'),
                    ElementExpiryDateRecord::tableName()
                );
                $this->dropIndex(
                    $this->getDb()->getIndexName(ElementExpiryDateRecord::tableName(), 'elementId', true),
                    ElementExpiryDateRecord::tableName()
                );
            }
            catch (\Exception $exception) {}

            $this->addPrimaryKey(null, ElementExpiryDateRecord::tableName(), ['elementId']);
            $this->addForeignKey(null, ElementExpiryDateRecord::tableName(), 'elementId', Element::tableName(), 'id', 'CASCADE', 'CASCADE');
        }

        if ($this->db->tableExists(ElementQueryCacheRecord::tableName())) {
            // Drop existing indexes first to avoid duplicate error (https://github.com/putyourlightson/craft-blitz/issues/240)
            try {
                $this->dropIndex(
                    $this->getDb()->getIndexName(ElementQueryCacheRecord::tableName(), ['cacheId', 'queryId'], true),
                    ElementQueryCacheRecord::tableName()
                );
            }
            catch (\Exception $exception) {}

            $this->addPrimaryKey(null, ElementQueryCacheRecord::tableName(), ['cacheId', 'queryId']);
        }

        if ($this->db->tableExists(ElementQuerySourceRecord::tableName())) {
            $this->addColumn(ElementQuerySourceRecord::tableName(), 'id', $this->primaryKey()->first());
        }

        if ($this->db->tableExists(CacheTagRecord::tableName())) {
            // Drop existing indexes first to avoid duplicate error (https://github.com/putyourlightson/craft-blitz/issues/240)
            try {
                $this->dropIndex(
                    $this->getDb()->getIndexName(CacheTagRecord::tableName(), ['cacheId', 'tag'], true),
                    CacheTagRecord::tableName()
                );
            }
            catch (\Exception $exception) {}

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
