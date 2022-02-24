<?php

namespace putyourlightson\blitz\migrations;

use Craft;
use craft\db\Migration;
use putyourlightson\blitz\records\ElementQueryRecord;

class m190105_120000_alter_index_column extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $table = ElementQueryRecord::tableName();

        if ($this->db->columnExists($table, 'index')) {
            $this->alterColumn($table, 'index', $this->bigInteger()->notNull());
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
