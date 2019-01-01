<?php

namespace putyourlightson\blitz\migrations;

use Craft;
use craft\db\Migration;
use putyourlightson\blitz\records\ElementQueryRecord;

class m181228_120000_optimise_elementqueries extends Migration
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $table = ElementQueryRecord::tableName();

        $this->delete($table);

        if ($this->db->columnExists($table, 'hash')) {
            $this->dropColumn($table, 'hash');
            $this->addColumn($table, 'index', $this->integer()->unsigned()->notNull()->after('id'));
            $this->createIndex(null, $table, 'index', true);
        }

        if ($this->db->columnExists($table, 'query')) {
            $this->dropColumn($table, 'query');
            $this->addColumn($table, 'params', $this->text()->after('type'));
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
