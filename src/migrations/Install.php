<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\migrations;

use Craft;
use craft\db\Migration;
use craft\records\Element;
use craft\records\Site;
use putyourlightson\blitz\models\SiteUriModel;
use putyourlightson\blitz\records\CacheRecord;
use putyourlightson\blitz\records\DriverDataRecord;
use putyourlightson\blitz\records\ElementCacheRecord;
use putyourlightson\blitz\records\ElementExpiryDateRecord;
use putyourlightson\blitz\records\ElementQueryCacheRecord;
use putyourlightson\blitz\records\ElementQueryRecord;
use putyourlightson\blitz\records\CacheTagRecord;
use Throwable;

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
     * @throws Throwable
     */
    public function safeDown(): bool
    {
        $this->dropTableIfExists(DriverDataRecord::tableName());
        $this->dropTableIfExists(ElementQueryCacheRecord::tableName());
        $this->dropTableIfExists(ElementQueryRecord::tableName());
        $this->dropTableIfExists(ElementCacheRecord::tableName());
        $this->dropTableIfExists(ElementExpiryDateRecord::tableName());
        $this->dropTableIfExists(CacheTagRecord::tableName());
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
                'expiryDate' => $this->dateTime(),
            ]);
        }

        if (!$this->db->tableExists(ElementCacheRecord::tableName())) {
            $this->createTable(ElementCacheRecord::tableName(), [
                'cacheId' => $this->integer()->notNull(),
                'elementId' => $this->integer()->notNull(),
            ]);
        }

        if (!$this->db->tableExists(ElementExpiryDateRecord::tableName())) {
            $this->createTable(ElementExpiryDateRecord::tableName(), [
                'elementId' => $this->integer()->notNull(),
                'expiryDate' => $this->dateTime(),
            ]);
        }

        if (!$this->db->tableExists(ElementQueryCacheRecord::tableName())) {
            $this->createTable(ElementQueryCacheRecord::tableName(), [
                'cacheId' => $this->integer()->notNull(),
                'queryId' => $this->integer()->notNull(),
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
        $this->createIndex(null, CacheRecord::tableName(), 'expiryDate', false);
        $this->createIndex(null, ElementExpiryDateRecord::tableName(), 'elementId', true);
        $this->createIndex(null, ElementExpiryDateRecord::tableName(), 'expiryDate', false);
        $this->createIndex(null, ElementQueryCacheRecord::tableName(), ['cacheId', 'queryId'], true);
        $this->createIndex(null, ElementQueryRecord::tableName(), 'index', true);
        $this->createIndex(null, ElementQueryRecord::tableName(), 'type', false);
        $this->createIndex(null, CacheTagRecord::tableName(), 'tag', false);
    }

    /**
     * Creates the foreign keys needed for the Records used by the plugin
     *
     * @return void
     */
    protected function addForeignKeys()
    {
        $this->addForeignKey(null, CacheRecord::tableName(), 'siteId', Site::tableName(), 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, ElementCacheRecord::tableName(), 'cacheId', CacheRecord::tableName(), 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, ElementCacheRecord::tableName(), 'elementId', Element::tableName(), 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, ElementExpiryDateRecord::tableName(), 'elementId', Element::tableName(), 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, ElementQueryCacheRecord::tableName(), 'cacheId', CacheRecord::tableName(), 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, ElementQueryCacheRecord::tableName(), 'queryId', ElementQueryRecord::tableName(), 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, CacheTagRecord::tableName(), 'cacheId', CacheRecord::tableName(), 'id', 'CASCADE', 'CASCADE');
    }
}
