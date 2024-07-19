<?php

namespace putyourlightson\blitz\migrations;

use Craft;
use craft\db\Migration;
use putyourlightson\blitz\drivers\storage\FileStorage;

class m240719_120000_remove_legacy_settings extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $projectConfig = Craft::$app->getProjectConfig();
        $cacheStorageType = $projectConfig->get('plugins.blitz.settings.cacheStorageType') ?? null;

        if ($cacheStorageType === FileStorage::class) {
            $cacheStorageSettings = $projectConfig->get('plugins.blitz.settings.cacheStorageSettings') ?? [];

            foreach ($cacheStorageSettings as $key => $value) {
                if ($key === 'createGzipFiles' || $key === 'createBrotliFiles') {
                    $projectConfig->remove('plugins.blitz.settings.cacheStorageSettings.' . $key);
                }
            }
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
}
