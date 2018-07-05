<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\migrations;

use Craft;
use craft\db\Migration;
use putyourlightson\blitz\records\CacheRecord;
use putyourlightson\blitz\records\ElementCacheRecord;
use putyourlightson\blitz\records\ElementQueryCacheRecord;

class Install extends Migration
{
    // Public Methods
    // =========================================================================

    /**
     * @return boolean
     */
    public function safeUp(): bool
    {
        $cachesTable = CacheRecord::tableName();

        if (!$this->db->tableExists($cachesTable)) {
            $this->createTable($cachesTable, [
                'id' => $this->primaryKey(),
                'siteId' => $this->integer()->notNull(),
                'uri' => $this->string()->notNull(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);

            $this->createIndex(null, $cachesTable, ['siteId', 'uri'], true);

            $this->addForeignKey(null, $cachesTable, 'siteId', '{{%sites}}', 'id', 'CASCADE');
        }

        $elementCacheTable = ElementCacheRecord::tableName();

        if (!$this->db->tableExists($elementCacheTable)) {
            $this->createTable($elementCacheTable, [
                'id' => $this->primaryKey(),
                'cacheId' => $this->integer()->notNull(),
                'elementId' => $this->integer()->notNull(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);

            $this->addForeignKey(null, $elementCacheTable, 'cacheId', $cachesTable, 'id', 'CASCADE');
            $this->addForeignKey(null, $elementCacheTable, 'elementId', '{{%elements}}', 'id', 'CASCADE');
        }

        $elementQueryCacheTable = ElementQueryCacheRecord::tableName();

        if (!$this->db->tableExists($elementQueryCacheTable)) {
            $this->createTable($elementQueryCacheTable, [
                'id' => $this->primaryKey(),
                'cacheId' => $this->integer()->notNull(),
                'type' => $this->string()->notNull(),
                'query' => $this->longText(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);

            $this->addForeignKey(null, $elementQueryCacheTable, 'cacheId', $cachesTable, 'id', 'CASCADE');
        }

        // Refresh the db schema caches
        Craft::$app->db->schema->refresh();

        return true;
    }

    /**
     * @return boolean
     * @throws \Throwable
     */
    public function safeDown(): bool
    {
        $this->dropTableIfExists(ElementCacheRecord::tableName());
        $this->dropTableIfExists(ElementQueryCacheRecord::tableName());
        $this->dropTableIfExists(CacheRecord::tableName());

        return true;
    }
}
