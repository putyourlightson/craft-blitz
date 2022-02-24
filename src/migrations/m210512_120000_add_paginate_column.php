<?php

namespace putyourlightson\blitz\migrations;

use craft\db\Migration;
use putyourlightson\blitz\records\CacheRecord;

class m210512_120000_add_paginate_column extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $table = CacheRecord::tableName();

        if (!$this->db->columnExists($table, 'paginate')) {
            $this->addColumn($table, 'paginate', $this->integer()->after('uri'));
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo self::class." cannot be reverted.\n";

        return false;
    }
}
