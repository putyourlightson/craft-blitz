<?php

namespace putyourlightson\blitz\migrations;

use craft\db\Migration;

class m180704_120000_add_caches_elementquerycaches_tables extends Migration
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        // Recreate tables from install migration
        $install = new Install();
        $install->safeDown();
        $install->safeUp();
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m180704_120000_add_caches_elementquerycaches_tables cannot be reverted.\n";

        return false;
    }
}
