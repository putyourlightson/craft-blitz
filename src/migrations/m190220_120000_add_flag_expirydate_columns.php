<?php

namespace putyourlightson\blitz\migrations;

use Craft;
use craft\db\Migration;
use putyourlightson\blitz\records\CacheRecord;
use putyourlightson\blitz\records\ElementCacheRecord;

class m190220_120000_add_flag_expirydate_columns extends Migration
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $table = CacheRecord::tableName();

        if (!$this->db->columnExists($table, 'flag')) {
            $this->addColumn($table, 'flag', $this->string()->after('uri'));
            $this->createIndex(null, $table, 'flag', false);
        }

        if (!$this->db->columnExists($table, 'expiryDate')) {
            $this->addColumn($table, 'expiryDate', $this->dateTime()->after('flag'));
            $this->createIndex(null, $table, 'expiryDate', false);
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
