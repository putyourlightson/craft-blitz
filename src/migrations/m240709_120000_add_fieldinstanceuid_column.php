<?php

namespace putyourlightson\blitz\migrations;

use craft\db\Migration;
use putyourlightson\blitz\records\ElementFieldCacheRecord;
use putyourlightson\blitz\records\ElementQueryFieldRecord;

class m240709_120000_add_fieldinstanceuid_column extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        if ($this->db->columnExists(ElementFieldCacheRecord::tableName(), 'fieldId')) {
            ElementFieldCacheRecord::deleteAll();
            $this->dropColumn(ElementFieldCacheRecord::tableName(), 'fieldId');
            $this->addColumn(ElementFieldCacheRecord::tableName(), 'fieldInstanceUid', $this->uid());
        }

        if ($this->db->columnExists(ElementQueryFieldRecord::tableName(), 'fieldId')) {
            ElementQueryFieldRecord::deleteAll();
            $this->dropColumn(ElementQueryFieldRecord::tableName(), 'fieldId');
            $this->addColumn(ElementQueryFieldRecord::tableName(), 'fieldInstanceUid', $this->uid());
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
