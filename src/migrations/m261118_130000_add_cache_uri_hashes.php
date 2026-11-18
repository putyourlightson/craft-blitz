<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\migrations;

use craft\db\Migration;
use craft\helpers\Db;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\records\CacheRecord;
use RuntimeException;

class m261118_130000_add_cache_uri_hashes extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $table = CacheRecord::tableName();
        if (Db::findIndex($table, ['uriHash'], true, $this->db)) {
            return true;
        }

        // Cache metadata can be rebuilt, avoiding a full-table hash backfill.
        Blitz::$plugin->flushCache->flushAll();
        if (CacheRecord::find()->exists()) {
            throw new RuntimeException('Unable to flush the Blitz cache before adding URI hashes.');
        }

        if (!$this->db->columnExists($table, 'uriHash')) {
            $this->addColumn($table, 'uriHash', $this->char(32)->notNull());
        } else {
            $this->alterColumn($table, 'uriHash', $this->char(32)->notNull());
        }
        $this->createIndex(null, $table, 'uriHash', true);

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        $table = CacheRecord::tableName();
        if ($this->db->columnExists($table, 'uriHash')) {
            if ($index = Db::findIndex($table, ['uriHash'], true, $this->db)) {
                $this->dropIndex($index, $table);
            }
            $this->dropColumn($table, 'uriHash');
        }

        return true;
    }
}
