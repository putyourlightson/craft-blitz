<?php

namespace putyourlightson\blitz\migrations;

use Craft;
use craft\db\Migration;
use putyourlightson\blitz\records\ElementCacheRecord;

class m180628_120000_add_elementcaches_table extends Migration
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $elementCacheTable = ElementCacheRecord::tableName();

        if (!$this->db->tableExists($elementCacheTable)) {
            $this->createTable($elementCacheTable, [
                'id' => $this->primaryKey(),
                'elementId' => $this->integer()->notNull(),
                'uri' => $this->string()->notNull(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);

            $this->createIndex(null, $elementCacheTable, ['elementId', 'uri'], true);

            $this->addForeignKey(null, $elementCacheTable, 'elementId', '{{%elements}}', 'id', 'CASCADE');

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
        echo "m180628_120000_add_elementcaches_table cannot be reverted.\n";

        return false;
    }
}
