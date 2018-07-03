<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\migrations;

use Craft;
use craft\db\Migration;
use putyourlightson\blitz\records\ElementCacheRecord;

class Install extends Migration
{
    // Public Methods
    // =========================================================================

    /**
     * @return boolean
     */
    public function safeUp(): bool
    {
        $elementCacheTable = ElementCacheRecord::tableName();

        if (!$this->db->tableExists($elementCacheTable)) {
            $this->createTable($elementCacheTable, [
                'id' => $this->primaryKey(),
                'elementId' => $this->integer()->notNull(),
                'siteId' => $this->integer()->notNull(),
                'uri' => $this->string()->notNull(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);

            $this->createIndex(null, $elementCacheTable, ['elementId', 'siteId', 'uri'], true);

            $this->addForeignKey(null, $elementCacheTable, 'elementId', '{{%elements}}', 'id', 'CASCADE');
            $this->addForeignKey(null, $elementCacheTable, 'siteId', '{{%sites}}', 'id', 'CASCADE');

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
        $this->dropTableIfExists(ElementCacheRecord::tableName());

        return true;
    }
}
