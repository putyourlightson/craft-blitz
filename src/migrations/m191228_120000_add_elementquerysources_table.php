<?php

namespace putyourlightson\blitz\migrations;

use Craft;
use craft\db\Migration;
use putyourlightson\blitz\records\ElementQueryRecord;
use putyourlightson\blitz\records\ElementQuerySourceRecord;

class m191228_120000_add_elementquerysources_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        if (!$this->db->tableExists(ElementQuerySourceRecord::tableName())) {
            $this->createTable(ElementQuerySourceRecord::tableName(), [
                'sourceId' => $this->integer(),
                'queryId' => $this->integer()->notNull(),
            ]);

            $this->createIndex(null, ElementQuerySourceRecord::tableName(), ['sourceId', 'queryId'], true);

            $this->addForeignKey(null, ElementQuerySourceRecord::tableName(), 'queryId', ElementQueryRecord::tableName(), 'id', 'CASCADE', 'CASCADE');

            // Copy element query IDs into sources table
            $queryIds = ElementQueryRecord::find()
                ->select('id')
                ->column();

            // Use DB connection, so we can exclude audit columns when inserting
            $db = Craft::$app->getDb();

            foreach ($queryIds as $queryId) {
                $db->createCommand()
                    ->insert(ElementQuerySourceRecord::tableName(), [
                        'sourceId' => null,
                        'queryId' => $queryId,
                    ], false)
                    ->execute();
            }
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
