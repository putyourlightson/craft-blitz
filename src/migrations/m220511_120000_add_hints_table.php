<?php

namespace putyourlightson\blitz\migrations;

use craft\db\Migration;
use putyourlightson\blitzhints\migrations\Install as HintsInstall;

class m220511_120000_add_hints_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        return (new HintsInstall())->safeUp();
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        return (new HintsInstall())->safeDown();
    }
}
