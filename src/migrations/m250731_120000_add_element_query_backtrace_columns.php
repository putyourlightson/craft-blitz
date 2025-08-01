<?php

namespace putyourlightson\blitz\migrations;

use craft\db\Migration;
use putyourlightson\blitz\records\ElementQueryRecord;

class m250731_120000_add_element_query_backtrace_columns extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $table = ElementQueryRecord::tableName();

        if (!$this->db->columnExists($table, 'template')) {
            $this->addColumn($table, 'template', $this->string());
        }
        if (!$this->db->columnExists($table, 'code')) {
            $this->addColumn($table, 'code', $this->text());
        }
        if (!$this->db->columnExists($table, 'backtrace')) {
            $this->addColumn($table, 'backtrace', $this->text());
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo self::class . " cannot be reverted.\n";

        return true;
    }
}
