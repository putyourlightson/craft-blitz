<?php

namespace putyourlightson\blitz\migrations;

use Craft;
use craft\db\Migration;
use putyourlightson\blitz\records\ElementCacheRecord;

class m180703_120000_add_siteid_column extends Migration
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        // Recreate table from install migration
        $install = new Install();
        $install->safeDown();
        $install->safeUp();
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
