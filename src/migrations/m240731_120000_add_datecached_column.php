<?php

namespace putyourlightson\blitz\migrations;

use craft\db\Migration;
use putyourlightson\blitz\records\CacheRecord;

class m240731_120000_add_datecached_column extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        if (!$this->db->columnExists(CacheRecord::tableName(), 'dateCached')) {
            $this->addColumn(CacheRecord::tableName(), 'dateCached', $this->dateTime()->after('paginate'));
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
