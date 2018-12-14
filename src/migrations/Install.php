<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\migrations;

use Craft;
use craft\db\Migration;
use craft\records\Element;
use craft\records\Site;
use putyourlightson\blitz\records\CacheRecord;
use putyourlightson\blitz\records\ElementCacheRecord;
use putyourlightson\blitz\records\ElementQueryCacheRecord;
use putyourlightson\blitz\records\ElementQueryRecord;

class Install extends Migration
{
    // Public Methods
    // =========================================================================

    /**
     * @return boolean
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
     * @return boolean
     * @throws \Throwable
     */
    public function safeDown(): bool
    {
        $this->dropTableIfExists(ElementQueryRecord::tableName());
        $this->dropTableIfExists(ElementQueryCacheRecord::tableName());
        $this->dropTableIfExists(ElementCacheRecord::tableName());
        $this->dropTableIfExists(CacheRecord::tableName());

        return true;
    }

    // Protected Methods
    // =========================================================================

    /**
     * Creates the tables needed for the Records used by the plugin
     *
     * @return boolean
     */
    protected function createTables(): bool
    {
        if (!$this->db->tableExists(CacheRecord::tableName())) {
            $this->createTable(CacheRecord::tableName(), [
                'id' => $this->primaryKey(),
                'siteId' => $this->integer()->notNull(),
                'uri' => $this->string()->notNull(),
            ]);
        }

        if (!$this->db->tableExists(ElementCacheRecord::tableName())) {
            $this->createTable(ElementCacheRecord::tableName(), [
                'id' => $this->primaryKey(),
                'cacheId' => $this->integer()->notNull(),
                'elementId' => $this->integer()->notNull(),
            ]);
        }

        if (!$this->db->tableExists(ElementQueryCacheRecord::tableName())) {
            $this->createTable(ElementQueryCacheRecord::tableName(), [
                'id' => $this->primaryKey(),
                'cacheId' => $this->integer()->notNull(),
                'queryId' => $this->integer()->notNull(),
            ]);
        }

        if (!$this->db->tableExists(ElementQueryRecord::tableName())) {
            $this->createTable(ElementQueryRecord::tableName(), [
                'id' => $this->primaryKey(),
                'type' => $this->string()->notNull(),
                'hash' => $this->string(),
                'query' => $this->longText(),
            ]);
        }

        return true;
    }

    /**
     * Creates the indexes needed for the Records used by the plugin
     *
     * @return void
     */
    protected function createIndexes()
    {
        $this->createIndex(null, CacheRecord::tableName(), ['siteId', 'uri'], true);
        $this->createIndex(null, ElementQueryRecord::tableName(), ['type'], false);
        $this->createIndex(null, ElementQueryCacheRecord::tableName(), ['cacheId', 'queryId'], true);
    }

    /**
     * Creates the foreign keys needed for the Records used by the plugin
     *
     * @return void
     */
    protected function addForeignKeys()
    {
        $this->addForeignKey(null, CacheRecord::tableName(), 'siteId', Site::tableName(), 'id', 'CASCADE');
        $this->addForeignKey(null, ElementCacheRecord::tableName(), 'cacheId', CacheRecord::tableName(), 'id', 'CASCADE');
        $this->addForeignKey(null, ElementCacheRecord::tableName(), 'elementId', Element::tableName(), 'id', 'CASCADE');
        $this->addForeignKey(null, ElementQueryCacheRecord::tableName(), 'cacheId', CacheRecord::tableName(), 'id', 'CASCADE');
        $this->addForeignKey(null, ElementQueryCacheRecord::tableName(), 'queryId', ElementQueryRecord::tableName(), 'id', 'CASCADE');
    }
}
