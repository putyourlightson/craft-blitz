<?php

namespace putyourlightson\blitz\migrations;

/**
 * Repairs primary keys for installations on which the original migration was only partially applied.
 */
class m260925_120000_fix_siteid_primary_keys extends m260911_120000_add_siteid_columns
{
    public function safeDown(): bool
    {
        echo self::class . " cannot be reverted.\n";

        return false;
    }
}
