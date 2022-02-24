<?php

namespace putyourlightson\blitz\migrations;

use craft\db\Migration;

class m180703_120000_add_siteid_column extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        // Recreate tables from install migration
        $install = new Install();
        $install->safeDown();
        $install->safeUp();

        return true;
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
