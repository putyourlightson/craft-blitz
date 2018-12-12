<?php

namespace putyourlightson\blitz\migrations;

use craft\db\Migration;
use putyourlightson\blitz\records\ElementQueryRecord;

class m181212_120000_add_elementids_column extends Migration
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $table = ElementQueryRecord::tableName();

        if (!$this->db->columnExists($table, 'elementIds')) {
            $this->addColumn($table, 'elementIds', $this->longText()->after('query'));
        }
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m180703_120000_add_siteid_column cannot be reverted.\n";

        return false;
    }
}
