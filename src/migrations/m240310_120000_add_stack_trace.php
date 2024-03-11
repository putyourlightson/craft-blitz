<?php

namespace putyourlightson\blitz\migrations;

use craft\db\Migration;
use putyourlightson\blitzhints\records\HintRecord;

class m240310_120000_add_stack_trace extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        if (!$this->db->columnExists(HintRecord::tableName(), 'stackTrace')) {
            $this->addColumn(HintRecord::tableName(), 'stackTrace', $this->text()->after('line'));
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
