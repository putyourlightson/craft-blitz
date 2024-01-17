<?php

namespace putyourlightson\blitz\migrations;

use craft\db\Migration;
use craft\helpers\App;
use craft\helpers\FileHelper;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\drivers\storage\FileStorage;

class m230705_120000_clear_compressed_cache_if_ssi_or_esi_enabled extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $cacheStorage = Blitz::$plugin->cacheStorage;

        if ($cacheStorage instanceof FileStorage
            && Blitz::$plugin->cacheStorage->compressCachedValues === true
            && (Blitz::$plugin->settings->ssiEnabled || Blitz::$plugin->settings->esiEnabled)
        ) {
            if (!empty($cacheStorage->folderPath) && is_dir($cacheStorage->folderPath)) {
                $cacheFolderPath = FileHelper::normalizePath(
                    App::parseEnv($cacheStorage->folderPath)
                );

                $compressedFiles = FileHelper::findFiles($cacheFolderPath, [
                    'only' => [
                        '*' . '.gz',
                    ],
                ]);

                foreach ($compressedFiles as $file) {
                    unlink($file);
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
