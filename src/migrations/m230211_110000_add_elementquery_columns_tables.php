<?php

namespace putyourlightson\blitz\migrations;

use craft\db\Migration;
use craft\records\Field;
use putyourlightson\blitz\records\ElementQueryAttributeRecord;
use putyourlightson\blitz\records\ElementQueryFieldRecord;
use putyourlightson\blitz\records\ElementQueryRecord;
use putyourlightson\blitz\records\ElementQuerySourceRecord;

class m230211_110000_add_elementquery_columns_tables extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        if ($this->db->tableExists(ElementQuerySourceRecord::tableName())) {
            $this->dropTable(ElementQuerySourceRecord::tableName());
            $this->createTable(ElementQuerySourceRecord::tableName(), [
                'queryId' => $this->integer()->notNull(),
                'sourceId' => $this->integer()->notNull(),
                'PRIMARY KEY([[queryId]], [[sourceId]])',
            ]);

            $this->addForeignKey(null, ElementQuerySourceRecord::tableName(), 'queryId', ElementQueryRecord::tableName(), 'id', 'CASCADE', 'CASCADE');
        }

        if (!$this->db->tableExists(ElementQueryAttributeRecord::tableName())) {
            $this->createTable(ElementQueryAttributeRecord::tableName(), [
                'queryId' => $this->integer()->notNull(),
                'attribute' => $this->string()->notNull(),
                'PRIMARY KEY([[queryId]], [[attribute]])',
            ]);

            $this->addForeignKey(null, ElementQueryAttributeRecord::tableName(), 'queryId', ElementQueryRecord::tableName(), 'id', 'CASCADE', 'CASCADE');
        }

        if (!$this->db->tableExists(ElementQueryFieldRecord::tableName())) {
            $this->createTable(ElementQueryFieldRecord::tableName(), [
                'queryId' => $this->integer()->notNull(),
                'fieldId' => $this->integer()->notNull(),
                'PRIMARY KEY([[queryId]], [[fieldId]])',
            ]);

            $this->addForeignKey(null, ElementQueryFieldRecord::tableName(), 'queryId', ElementQueryRecord::tableName(), 'id', 'CASCADE', 'CASCADE');
            $this->addForeignKey(null, ElementQueryFieldRecord::tableName(), 'fieldId', Field::tableName(), 'id', 'CASCADE', 'CASCADE');
        }

        // Delete all element query records so that associated attributes and
        // fields will be regenerated, rather than doing it via a migration,
        // as a cache clear alone will not delete these records.
        ElementQueryRecord::deleteAll();

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
