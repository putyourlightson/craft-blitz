<?php

namespace putyourlightson\blitz\migrations;

use craft\db\Migration;
use putyourlightson\blitz\models\BaseDataModel;
use putyourlightson\blitz\records\ElementQueryRecord;
use putyourlightson\blitz\records\ElementQuerySiteRecord;

class m260913_120000_add_elementquerysites_table extends Migration
{
    public function safeUp(): bool
    {
        $table = ElementQuerySiteRecord::tableName();

        if (!$this->db->tableExists($table)) {
            $this->createTable($table, [
                'queryId' => $this->integer()->notNull(),
                'siteId' => $this->integer()->notNull()->defaultValue(BaseDataModel::SITE_ID_ANY),
                'PRIMARY KEY([[queryId]], [[siteId]])',
            ]);
            $this->addForeignKey(null, $table, 'queryId', ElementQueryRecord::tableName(), 'id', 'CASCADE', 'CASCADE');

            // Retain existing queries as unknown-site dependencies until the cache is cleared or refreshed.
            $this->execute(
                'INSERT INTO ' . $table . ' ([[queryId]], [[siteId]])'
                . ' SELECT [[id]], ' . BaseDataModel::SITE_ID_ANY
                . ' FROM ' . ElementQueryRecord::tableName()
            );
        }

        return true;
    }

    public function safeDown(): bool
    {
        $this->dropTableIfExists(ElementQuerySiteRecord::tableName());

        return true;
    }
}
