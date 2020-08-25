<?php

namespace putyourlightson\blitz\migrations;

use craft\db\Migration;
use craft\helpers\MigrationHelper;
use craft\records\Element;
use putyourlightson\blitz\records\CacheRecord;
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
            // Drop existing indexes first to avoid duplicate error (https://github.com/putyourlightson/craft-blitz/issues/240)
            MigrationHelper::dropIndexIfExists(ElementCacheRecord::tableName(), ['cacheId', 'elementId'], true, $this);

            $this->addPrimaryKey(null, ElementCacheRecord::tableName(), ['cacheId', 'elementId']);
        }

        if ($this->db->tableExists(ElementExpiryDateRecord::tableName())) {
            // Drop existing indexes first to avoid duplicate error (https://github.com/putyourlightson/craft-blitz/issues/240)
            MigrationHelper::dropForeignKeyIfExists(ElementExpiryDateRecord::tableName(), ['elementId'], $this);
            MigrationHelper::dropIndexIfExists(ElementExpiryDateRecord::tableName(), ['elementId'], true, $this);

            $this->addPrimaryKey(null, ElementExpiryDateRecord::tableName(), ['elementId']);

            $this->addForeignKey(null, ElementExpiryDateRecord::tableName(), 'elementId', Element::tableName(), 'id', 'CASCADE', 'CASCADE');
        }

        if ($this->db->tableExists(ElementQueryCacheRecord::tableName())) {
            // Drop existing indexes first to avoid duplicate error (https://github.com/putyourlightson/craft-blitz/issues/240)
            MigrationHelper::dropForeignKeyIfExists(ElementQueryCacheRecord::tableName(), ['cacheId'], $this);
            MigrationHelper::dropForeignKeyIfExists(ElementQueryCacheRecord::tableName(), ['queryId'], $this);
            MigrationHelper::dropIndexIfExists(ElementQueryCacheRecord::tableName(), ['cacheId', 'queryId'], true, $this);

            $this->addPrimaryKey(null, ElementQueryCacheRecord::tableName(), ['cacheId', 'queryId']);

            $this->addForeignKey(null, ElementQueryCacheRecord::tableName(), 'cacheId', CacheRecord::tableName(), 'id', 'CASCADE', 'CASCADE');
           $this->addForeignKey(null, ElementQueryCacheRecord::tableName(), 'queryId', ElementQueryRecord::tableName(), 'id', 'CASCADE', 'CASCADE');
        }

        if ($this->db->tableExists(ElementQuerySourceRecord::tableName())) {
            $this->addColumn(ElementQuerySourceRecord::tableName(), 'id', $this->primaryKey()->first());
        }

        if ($this->db->tableExists(CacheTagRecord::tableName())) {
            // Drop existing indexes first to avoid duplicate error (https://github.com/putyourlightson/craft-blitz/issues/240)
            MigrationHelper::dropIndexIfExists(CacheTagRecord::tableName(), ['cacheId', 'tag'], true, $this);

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
