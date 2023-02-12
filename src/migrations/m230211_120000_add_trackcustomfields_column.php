<?php

namespace putyourlightson\blitz\migrations;

use craft\db\Migration;
use putyourlightson\blitz\records\ElementCacheRecord;

class m230211_120000_add_trackcustomfields_column extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        if (!$this->db->columnExists(ElementCacheRecord::tableName(), 'trackCustomFields')) {
            $this->addColumn(
                ElementCacheRecord::tableName(),
                'trackCustomFields',
                $this->string()->defaultValue(null)->after('elementId'),
            );
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
