<?php

namespace putyourlightson\blitz\migrations;

use Craft;
use craft\db\Migration;
use putyourlightson\blitz\records\CacheRecord;
use putyourlightson\blitz\records\CacheTagRecord;

class m190227_120000_add_cachetags_table extends Migration
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

        $table = CacheTagRecord::tableName();

        if (!$this->db->tableExists($table)) {
            $this->createTable($table, [
                'cacheId' => $this->integer()->notNull(),
                'tag' => $this->string()->notNull(),
            ]);

            $this->createIndex(null, $table, 'tag', false);

            $this->addForeignKey(null, $table, 'cacheId', CacheRecord::tableName(), 'id', 'CASCADE', 'CASCADE');
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
