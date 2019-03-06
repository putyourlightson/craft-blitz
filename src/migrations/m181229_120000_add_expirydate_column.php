<?php

namespace putyourlightson\blitz\migrations;

use Craft;
use craft\db\Migration;
use putyourlightson\blitz\records\CacheRecord;

class m181229_120000_add_expirydate_column extends Migration
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $table = CacheRecord::tableName();

        if (!$this->db->columnExists($table, 'expiryDate')) {
            $this->addColumn($table, 'expiryDate', $this->dateTime()->after('uri'));
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
