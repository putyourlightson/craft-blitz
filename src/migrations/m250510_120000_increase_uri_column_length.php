<?php

namespace putyourlightson\blitz\migrations;

use Craft;
use craft\db\Migration;
use craft\helpers\Db;
use craft\records\Site;
use putyourlightson\blitz\records\CacheRecord;

class m250510_120000_increase_uri_column_length extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $maxUriLength = 2048;
        $projectConfig = Craft::$app->getProjectConfig();
        $projectConfig->set('plugins.blitz.settings.maxUriLength', $maxUriLength);

        if ($this->db->columnExists(CacheRecord::tableName(), 'uri')) {
            $this->dropForeignKeysAndIndexes();
            $this->alterColumn(CacheRecord::tableName(), 'uri', $this->string($maxUriLength)->notNull());

            // Create a length-limited index on the URI column
            $maxUriIndexLength = 767;
            $uriColumn = "uri($maxUriIndexLength)";
            if (Craft::$app->getDb()->getIsPgsql()) {
                // Use a functional index for Postgres
                $uriColumn = "LEFT(uri, $maxUriIndexLength)";
            }
            $this->createIndex(null, CacheRecord::tableName(), ['siteId', $uriColumn]);

            $this->addForeignKey(null, CacheRecord::tableName(), 'siteId', Site::tableName(), 'id', 'CASCADE', 'CASCADE');
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

    /**
     * Drops foreign keys and indexes in a while loop to ensure duplicates are also removed.
     */
    private function dropForeignKeysAndIndexes(): void
    {
        while ($name = Db::findForeignKey(CacheRecord::tableName(), 'siteId')) {
            $this->dropForeignKey($name, CacheRecord::tableName());
        }

        while ($name = Db::findIndex(CacheRecord::tableName(), ['siteId', 'uri'], true)) {
            $this->dropIndex($name, CacheRecord::tableName());
        }
    }
}
