<?php

namespace putyourlightson\blitz\migrations;

use Craft;
use craft\db\Migration;
use putyourlightson\blitz\records\CacheRecord;
use putyourlightson\blitz\records\CacheFlagRecord;

class m190227_120000_add_cacheflags_table extends Migration
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $table = CacheFlagRecord::tableName();

        if (!$this->db->tableExists($table)) {
            $this->createTable($table, [
                'cacheId' => $this->integer()->notNull(),
                'flag' => $this->string()->notNull(),
            ]);

            $this->createIndex(null, $table, 'flag', false);

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
