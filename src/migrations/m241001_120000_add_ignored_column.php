<?php

namespace putyourlightson\blitz\migrations;

use craft\db\Migration;
use putyourlightson\blitz\records\HintRecord;

class m241001_120000_add_ignored_column extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        if (!$this->db->columnExists(HintRecord::tableName(), 'ignored')) {
            $this->addColumn(HintRecord::tableName(), 'ignored', $this->boolean()->defaultValue(false)->after('stackTrace'));
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
