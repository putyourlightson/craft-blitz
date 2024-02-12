<?php

namespace putyourlightson\blitz\migrations;

use craft\db\Migration;

class m240212_120000_clear_out_legacy_cached_element_types extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $migration = new m240110_120000_clear_out_legacy_cached_element_types();
        $migration->safeUp();

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
