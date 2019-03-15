<?php

namespace putyourlightson\blitz\migrations;

use Craft;
use craft\records\Element;
use craft\db\Migration;
use putyourlightson\blitz\records\ElementExpiryDateRecord;

class m190105_120000_add_elementexpirydates_table extends Migration
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        // Don't make the same config changes twice
        $projectConfig = Craft::$app->getProjectConfig();
        $schemaVersion = $projectConfig->get('plugins.blitz.schemaVersion', true);

        if (version_compare($schemaVersion, '2.0.0', '>=')) {
            return;
        }

        $table = ElementExpiryDateRecord::tableName();

        if (!$this->db->tableExists($table)) {
            $this->createTable($table, [
                'elementId' => $this->integer()->notNull(),
                'expiryDate' => $this->dateTime(),
            ]);

            $this->createIndex(null, $table, 'elementId', true);
            $this->createIndex(null, $table, 'expiryDate', false);

            $this->addForeignKey(null, $table, 'elementId', Element::tableName(), 'id', 'CASCADE', 'CASCADE');
        }

        // Refresh the db schema caches
        Craft::$app->db->schema->refresh();
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
