<?php

namespace putyourlightson\blitz\migrations;

use craft\db\Migration;
use putyourlightson\blitz\records\HintRecord;

class m240226_120000_drop_route_variable_hints_and_column extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        if ($this->db->columnExists(HintRecord::tableName(), 'routeVariable')) {
            HintRecord::deleteAll(['not', ['routeVariable' => '']]);

            $this->dropColumn(HintRecord::tableName(), 'routeVariable');
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo self::class . " cannot be reverted.\n";

        return false;
    }
}
