<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\migrations;

use Craft;
use craft\db\Migration;
use craft\helpers\Db;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\records\CacheRecord;
use RuntimeException;

class m261118_130000_update_cache_uris extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $table = CacheRecord::tableName();
        $hasHashIndex = Db::findIndex($table, ['uriHash'], true, $this->db) !== null;
        if (!$hasHashIndex) {
            // Cache metadata can be rebuilt, avoiding a full-table hash backfill.
            Blitz::$plugin->flushCache->flushAll();
            if (CacheRecord::find()->exists()) {
                throw new RuntimeException('Unable to flush the Blitz cache before adding URI hashes.');
            }
        }

        $column = $this->db->getTableSchema($table, true)?->columns['uri'] ?? null;
        if ($column !== null && $column->type !== 'text') {
            $collation = '"C"';
            if ($this->db->getIsMysql()) {
                $charset = Craft::$app->getConfig()->getDb()->getCharset();
                $collation = $charset === 'binary' ? 'binary' : $charset . '_bin';
            }

            // Match the case- and accent-sensitive URI column used by fresh installs.
            $uriColumn = $this->text()
                ->append('COLLATE ' . $collation . ' NOT NULL');
            $this->alterColumn($table, 'uri', $uriColumn);
        }

        if (!$hasHashIndex) {
            if (!$this->db->columnExists($table, 'uriHash')) {
                $this->addColumn($table, 'uriHash', $this->char(32)->notNull()->after('uri'));
            } else {
                $this->alterColumn($table, 'uriHash', $this->char(32)->notNull()->after('uri'));
            }
            $this->createIndex(null, $table, 'uriHash', true);
        }

        if (Db::findIndex($table, 'siteId', db: $this->db) === null) {
            $this->createIndex(null, $table, 'siteId');
        }

        $projectConfig = Craft::$app->getProjectConfig();
        $projectConfig->remove('plugins.blitz.settings.maxUriLength');
        $projectConfig->remove('plugins.blitz.settings.maxUriIndexLength');

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo self::class . " cannot be reverted.\n";

        return false;
    }
}
