<?php

namespace putyourlightson\blitz\migrations;

use Craft;
use craft\db\Migration;
use putyourlightson\blitz\records\CacheRecord;
use putyourlightson\blitz\records\ElementCacheRecord;
use putyourlightson\blitz\records\ElementQueryCacheRecord;
use putyourlightson\blitz\records\ElementQueryRecord;

class m181213_120000_remove_id_audit_columns extends Migration
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        // Create new indexes
        $this->createIndex(null, ElementCacheRecord::tableName(), ['cacheId', 'elementId'], true);
        $this->createIndex(null, ElementQueryCacheRecord::tableName(), ['cacheId', 'queryId'], true);

        // Remove ID columns
        $tables = [
            ElementCacheRecord::tableName(),
            ElementQueryCacheRecord::tableName(),
        ];
        foreach ($tables as $table) {
            if ($this->db->columnExists($table, 'id')) {
                $this->dropColumn($table, 'id');
            }
        }

        // Remove audit columns
        $tables = [
            CacheRecord::tableName(),
            ElementCacheRecord::tableName(),
            ElementQueryRecord::tableName(),
            ElementQueryCacheRecord::tableName(),
        ];

        foreach ($tables as $table) {
            if ($this->db->columnExists($table, 'dateCreated')) {
                $this->dropColumn($table, 'dateCreated');
            }
            if ($this->db->columnExists($table, 'dateUpdated')) {
                $this->dropColumn($table, 'dateUpdated');
            }
            if ($this->db->columnExists($table, 'uid')) {
                $this->dropColumn($table, 'uid');
            }
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
