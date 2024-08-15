<?php

namespace putyourlightson\blitz\migrations;

use craft\db\Migration;
use putyourlightson\blitz\records\ElementFieldCacheRecord;
use putyourlightson\blitz\records\ElementQueryFieldRecord;

class m240815_120000_add_fieldinstanceuid_column extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->dropTableIfExists(ElementFieldCacheRecord::tableName());
        $this->createTable(ElementFieldCacheRecord::tableName(), [
            'cacheId' => $this->integer()->notNull(),
            'elementId' => $this->integer()->notNull(),
            'fieldInstanceUid' => $this->uid(),
            'PRIMARY KEY([[cacheId]], [[elementId]], [[fieldInstanceUid]])',
        ]);

        $this->dropTableIfExists(ElementQueryFieldRecord::tableName());
        $this->createTable(ElementQueryFieldRecord::tableName(), [
            'queryId' => $this->integer()->notNull(),
            'fieldInstanceUid' => $this->uid(),
            'PRIMARY KEY([[queryId]], [[fieldInstanceUid]])',
        ]);

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
