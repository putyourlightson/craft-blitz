<?php

namespace putyourlightson\blitz\migrations;

use craft\db\Migration;
use putyourlightson\blitz\records\ElementQuerySourceRecord;

class m191228_120000_add_elementquerysourcestable extends Migration
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        if (!$this->db->tableExists(ElementQuerySourceRecord::tableName())) {
            $this->createTable(ElementQuerySourceRecord::tableName(), [
                'sourceId' => $this->integer()->notNull(),
                'queryId' => $this->integer()->notNull(),
            ]);
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
