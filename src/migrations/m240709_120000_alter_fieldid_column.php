<?php

namespace putyourlightson\blitz\migrations;

use craft\db\Migration;
use putyourlightson\blitz\records\ElementFieldCacheRecord;
use putyourlightson\blitz\records\ElementQueryFieldRecord;

class m240709_120000_alter_fieldid_column extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        if ($this->db->columnExists(ElementFieldCacheRecord::tableName(), 'fieldId')) {
            ElementFieldCacheRecord::deleteAll();

            $this->dropForeignKeyIfExists(ElementFieldCacheRecord::tableName(), 'fieldId');
            $this->renameColumn(ElementFieldCacheRecord::tableName(), 'fieldId', 'fieldInstanceUid');
            $this->alterColumn(ElementFieldCacheRecord::tableName(), 'fieldInstanceUid', $this->uid());
        }

        if ($this->db->columnExists(ElementQueryFieldRecord::tableName(), 'fieldId')) {
            ElementQueryFieldRecord::deleteAll();

            $this->dropForeignKeyIfExists(ElementQueryFieldRecord::tableName(), 'fieldId');
            $this->renameColumn(ElementQueryFieldRecord::tableName(), 'fieldId', 'fieldInstanceUid');
            $this->alterColumn(ElementQueryFieldRecord::tableName(), 'fieldInstanceUid', $this->uid());
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
