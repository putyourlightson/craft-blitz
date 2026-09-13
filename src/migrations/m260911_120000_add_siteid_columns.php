<?php

namespace putyourlightson\blitz\migrations;

use craft\db\Migration;
use putyourlightson\blitz\models\BaseDataModel;
use putyourlightson\blitz\records\ElementCacheRecord;
use putyourlightson\blitz\records\ElementFieldCacheRecord;

class m260911_120000_add_siteid_columns extends Migration
{
    public function safeUp(): bool
    {
        $tables = [ElementCacheRecord::tableName(), ElementFieldCacheRecord::tableName()];

        foreach ($tables as $table) {
            if (!$this->db->columnExists($table, 'siteId')) {
                // Retain old dependencies as unknown-site dependencies until the page is generated again.
                $this->addColumn($table, 'siteId', $this->integer()->notNull()->defaultValue(BaseDataModel::SITE_ID_ANY)->after('elementId'));

                // MySQL needs a separate index for the cacheId foreign key while replacing the primary key.
                $primaryKey = $this->db->getSchema()->getTablePrimaryKey($table);
                $this->createIndex(null, $table, ['cacheId']);
                $this->dropPrimaryKey($primaryKey->name, $table);
                $columns = ['cacheId', 'elementId', 'siteId'];
                if ($table === ElementFieldCacheRecord::tableName()) {
                    $columns[] = 'fieldInstanceUid';
                }
                $this->addPrimaryKey($primaryKey->name, $table, $columns);
            }
        }

        return true;
    }

    public function safeDown(): bool
    {
        echo self::class . " cannot be reverted.\n";

        return false;
    }
}
