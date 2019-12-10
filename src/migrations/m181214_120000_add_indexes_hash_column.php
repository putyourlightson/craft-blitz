<?php

namespace putyourlightson\blitz\migrations;

use Craft;
use craft\db\Migration;
use putyourlightson\blitz\records\ElementCacheRecord;
use putyourlightson\blitz\records\ElementQueryRecord;

class m181214_120000_add_indexes_hash_column extends Migration
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->createIndex(null, ElementCacheRecord::tableName(), ['cacheId', 'elementId'], true);

        $table = ElementQueryRecord::tableName();

        $this->delete($table);

        if (!$this->db->columnExists($table, 'hash')) {
            $this->addColumn($table, 'hash', $this->string()->notNull()->after('id'));

            $this->createIndex(null, $table, 'hash', true);
        }

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
