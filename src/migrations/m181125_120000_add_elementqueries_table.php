<?php

namespace putyourlightson\blitz\migrations;

use Craft;
use craft\db\Migration;
use putyourlightson\blitz\records\CacheRecord;
use putyourlightson\blitz\records\ElementQueryCacheRecord;
use putyourlightson\blitz\records\ElementQueryRecord;

class m181125_120000_add_elementqueries_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->dropTableIfExists(ElementQueryCacheRecord::tableName());

        $this->createTable(ElementQueryCacheRecord::tableName(), [
            'id' => $this->primaryKey(),
            'cacheId' => $this->integer()->notNull(),
            'queryId' => $this->integer()->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        if (!$this->db->tableExists(ElementQueryRecord::tableName())) {
            $this->createTable(ElementQueryRecord::tableName(), [
                'id' => $this->primaryKey(),
                'type' => $this->string()->notNull(),
                'query' => $this->longText(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);
        }

        $this->createIndex(null, ElementQueryCacheRecord::tableName(), ['cacheId', 'queryId'], true);

        $this->addForeignKey(null, ElementQueryCacheRecord::tableName(), 'cacheId', CacheRecord::tableName(), 'id', 'CASCADE');
        $this->addForeignKey(null, ElementQueryCacheRecord::tableName(), 'queryId', ElementQueryRecord::tableName(), 'id', 'CASCADE');

        // Refresh the db schema caches
        Craft::$app->db->schema->refresh();

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
