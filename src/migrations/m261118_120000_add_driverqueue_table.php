<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\migrations;

use craft\db\Migration;
use craft\records\Site;
use putyourlightson\blitz\records\DriverQueueRecord;
use putyourlightson\blitz\records\QueueSiteUriRecord;

class m261118_120000_add_driverqueue_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $table = QueueSiteUriRecord::tableName();

        if (!$this->db->tableExists($table)) {
            $this->createTable($table, [
                'id' => $this->primaryKey(),
                'siteId' => $this->integer()->notNull(),
                'uri' => $this->text()->notNull(),
                'uriHash' => $this->char(32)->notNull(),
            ]);
            // Hash the full site/URI identity so long, case-sensitive URIs remain distinct on both databases.
            $this->createIndex(null, $table, 'uriHash', true);
            $this->addForeignKey(null, $table, 'siteId', Site::tableName(), 'id', 'CASCADE', 'CASCADE');
        }

        $table = DriverQueueRecord::tableName();

        if (!$this->db->tableExists($table)) {
            $this->createTable($table, [
                'id' => $this->primaryKey(),
                'siteUriId' => $this->integer()->notNull(),
                'driverId' => $this->string()->notNull(),
                'driverMethod' => $this->string()->notNull(),
                'priority' => $this->integer()->notNull(),
                'revision' => $this->char(36)->notNull(),
            ]);
            $this->createIndex(null, $table, ['siteUriId', 'driverId', 'driverMethod'], true);
            $this->createIndex(null, $table, ['driverId', 'driverMethod', 'priority', 'id']);
            $this->addForeignKey(null, $table, 'siteUriId', QueueSiteUriRecord::tableName(), 'id', 'CASCADE', 'CASCADE');
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        $this->dropTableIfExists(DriverQueueRecord::tableName());
        $this->dropTableIfExists(QueueSiteUriRecord::tableName());

        return true;
    }
}
