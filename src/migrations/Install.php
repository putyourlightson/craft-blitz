<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\migrations;

use Craft;
use craft\db\Migration;
use craft\records\Element;
use craft\records\Site;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\records\CacheRecord;
use putyourlightson\blitz\records\CacheTagRecord;
use putyourlightson\blitz\records\DriverDataRecord;
use putyourlightson\blitz\records\ElementCacheRecord;
use putyourlightson\blitz\records\ElementExpiryDateRecord;
use putyourlightson\blitz\records\ElementFieldCacheRecord;
use putyourlightson\blitz\records\ElementQueryAttributeRecord;
use putyourlightson\blitz\records\ElementQueryCacheRecord;
use putyourlightson\blitz\records\ElementQueryFieldRecord;
use putyourlightson\blitz\records\ElementQueryRecord;
use putyourlightson\blitz\records\ElementQuerySourceRecord;
use putyourlightson\blitz\records\HintRecord;
use putyourlightson\blitz\records\IncludeRecord;
use putyourlightson\blitz\records\SsiIncludeCacheRecord;

class Install extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        if ($this->createTables()) {
            $this->createIndexes();
            $this->addForeignKeys();

            // Refresh the db schema caches
            Craft::$app->db->schema->refresh();
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        $this->dropTableIfExists(DriverDataRecord::tableName());
        $this->dropTableIfExists(ElementQueryFieldRecord::tableName());
        $this->dropTableIfExists(ElementQueryAttributeRecord::tableName());
        $this->dropTableIfExists(ElementQuerySourceRecord::tableName());
        $this->dropTableIfExists(ElementQueryCacheRecord::tableName());
        $this->dropTableIfExists(ElementQueryRecord::tableName());
        $this->dropTableIfExists(ElementFieldCacheRecord::tableName());
        $this->dropTableIfExists(ElementCacheRecord::tableName());
        $this->dropTableIfExists(ElementExpiryDateRecord::tableName());
        $this->dropTableIfExists(CacheTagRecord::tableName());
        $this->dropTableIfExists(SsiIncludeCacheRecord::tableName());
        $this->dropTableIfExists(IncludeRecord::tableName());
        $this->dropTableIfExists(CacheRecord::tableName());
        $this->dropTableIfExists(HintRecord::tableName());

        return true;
    }

    /**
     * Creates the tables needed for the Records used by the plugin.
     */
    protected function createTables(): bool
    {
        if (!$this->db->tableExists(CacheRecord::tableName())) {
            $this->createTable(CacheRecord::tableName(), [
                'id' => $this->primaryKey(),
                'siteId' => $this->integer()->notNull(),
                'uri' => $this->string(Blitz::$plugin->settings->maxUriLength)->notNull(),
                'paginate' => $this->integer(),
                'dateCached' => $this->dateTime(),
                'expiryDate' => $this->dateTime(),
            ]);
        }

        if (!$this->db->tableExists(ElementCacheRecord::tableName())) {
            $this->createTable(ElementCacheRecord::tableName(), [
                'cacheId' => $this->integer()->notNull(),
                'elementId' => $this->integer()->notNull(),
                'PRIMARY KEY([[cacheId]], [[elementId]])',
            ]);
        }

        if (!$this->db->tableExists(ElementFieldCacheRecord::tableName())) {
            $this->createTable(ElementFieldCacheRecord::tableName(), [
                'cacheId' => $this->integer()->notNull(),
                'elementId' => $this->integer()->notNull(),
                'fieldInstanceUid' => $this->uid(),
                'PRIMARY KEY([[cacheId]], [[elementId]], [[fieldInstanceUid]])',
            ]);
        }

        if (!$this->db->tableExists(ElementExpiryDateRecord::tableName())) {
            $this->createTable(ElementExpiryDateRecord::tableName(), [
                'elementId' => $this->integer()->notNull(),
                'expiryDate' => $this->dateTime(),
                'PRIMARY KEY([[elementId]])',
            ]);
        }

        if (!$this->db->tableExists(ElementQueryCacheRecord::tableName())) {
            $this->createTable(ElementQueryCacheRecord::tableName(), [
                'cacheId' => $this->integer()->notNull(),
                'queryId' => $this->integer()->notNull(),
                'PRIMARY KEY([[cacheId]], [[queryId]])',
            ]);
        }

        if (!$this->db->tableExists(ElementQuerySourceRecord::tableName())) {
            $this->createTable(ElementQuerySourceRecord::tableName(), [
                'queryId' => $this->integer()->notNull(),
                'sourceId' => $this->integer()->notNull(),
                'PRIMARY KEY([[queryId]], [[sourceId]])',
            ]);
        }

        if (!$this->db->tableExists(ElementQueryAttributeRecord::tableName())) {
            $this->createTable(ElementQueryAttributeRecord::tableName(), [
                'queryId' => $this->integer()->notNull(),
                'attribute' => $this->string()->notNull(),
                'PRIMARY KEY([[queryId]], [[attribute]])',
            ]);
        }

        if (!$this->db->tableExists(ElementQueryFieldRecord::tableName())) {
            $this->createTable(ElementQueryFieldRecord::tableName(), [
                'queryId' => $this->integer()->notNull(),
                'fieldInstanceUid' => $this->uid(),
                'PRIMARY KEY([[queryId]], [[fieldInstanceUid]])',
            ]);
        }

        if (!$this->db->tableExists(ElementQueryRecord::tableName())) {
            $this->createTable(ElementQueryRecord::tableName(), [
                'id' => $this->primaryKey(),
                'index' => $this->bigInteger()->notNull(),
                'type' => $this->string()->notNull(),
                'params' => $this->text(),
            ]);
        }

        if (!$this->db->tableExists(CacheTagRecord::tableName())) {
            $this->createTable(CacheTagRecord::tableName(), [
                'cacheId' => $this->integer()->notNull(),
                'tag' => $this->string()->notNull(),
                'PRIMARY KEY([[cacheId]], [[tag]])',
            ]);
        }

        if (!$this->db->tableExists(DriverDataRecord::tableName())) {
            $this->createTable(DriverDataRecord::tableName(), [
                'id' => $this->primaryKey(),
                'driver' => $this->string()->notNull(),
                'data' => $this->text(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);
        }

        if (!$this->db->tableExists(IncludeRecord::tableName())) {
            $this->createTable(IncludeRecord::tableName(), [
                'id' => $this->primaryKey(),
                'index' => $this->bigInteger()->notNull(),
                'siteId' => $this->integer()->notNull(),
                'template' => $this->string()->notNull(),
                'params' => $this->text()->notNull(),
            ]);
        }

        if (!$this->db->tableExists(SsiIncludeCacheRecord::tableName())) {
            $this->createTable(SsiIncludeCacheRecord::tableName(), [
                'cacheId' => $this->integer()->notNull(),
                'includeId' => $this->integer()->notNull(),
                'PRIMARY KEY([[cacheId]], [[includeId]])',
            ]);
        }

        if (!$this->db->tableExists(HintRecord::tableName())) {
            $this->createTable(HintRecord::tableName(), [
                'id' => $this->primaryKey(),
                'fieldId' => $this->integer()->notNull(),
                'template' => $this->string()->notNull(),
                'line' => $this->integer(),
                'stackTrace' => $this->text(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);
        }

        return true;
    }

    /**
     * Creates the indexes needed for the Records used by the plugin.
     */
    protected function createIndexes(): void
    {
        $this->createIndex(null, CacheRecord::tableName(), ['siteId', 'uri'], true);
        $this->createIndex(null, CacheRecord::tableName(), 'expiryDate');
        $this->createIndex(null, ElementExpiryDateRecord::tableName(), 'elementId', true);
        $this->createIndex(null, ElementExpiryDateRecord::tableName(), 'expiryDate');
        $this->createIndex(null, ElementQueryRecord::tableName(), 'index', true);
        $this->createIndex(null, ElementQueryRecord::tableName(), 'type');
        $this->createIndex(null, CacheTagRecord::tableName(), 'tag');
        $this->createIndex(null, IncludeRecord::tableName(), 'index', true);

        // Exclude the line number from the index to avoid duplicate hints appearing when templates are edited and lines shifted around.
        $this->createIndex(null, HintRecord::tableName(), [
            'fieldId',
            'template',
        ], true);
    }

    /**
     * Creates the foreign keys needed for the Records used by the plugin.
     */
    protected function addForeignKeys(): void
    {
        $this->addForeignKey(null, CacheRecord::tableName(), 'siteId', Site::tableName(), 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, ElementCacheRecord::tableName(), 'cacheId', CacheRecord::tableName(), 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, ElementCacheRecord::tableName(), 'elementId', Element::tableName(), 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, ElementFieldCacheRecord::tableName(), 'cacheId', CacheRecord::tableName(), 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, ElementFieldCacheRecord::tableName(), 'elementId', Element::tableName(), 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, ElementExpiryDateRecord::tableName(), 'elementId', Element::tableName(), 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, ElementQueryCacheRecord::tableName(), 'cacheId', CacheRecord::tableName(), 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, ElementQueryCacheRecord::tableName(), 'queryId', ElementQueryRecord::tableName(), 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, ElementQuerySourceRecord::tableName(), 'queryId', ElementQueryRecord::tableName(), 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, ElementQueryAttributeRecord::tableName(), 'queryId', ElementQueryRecord::tableName(), 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, ElementQueryFieldRecord::tableName(), 'queryId', ElementQueryRecord::tableName(), 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, CacheTagRecord::tableName(), 'cacheId', CacheRecord::tableName(), 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, SsiIncludeCacheRecord::tableName(), 'cacheId', CacheRecord::tableName(), 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, SsiIncludeCacheRecord::tableName(), 'includeId', IncludeRecord::tableName(), 'id', 'CASCADE', 'CASCADE');
    }
}
