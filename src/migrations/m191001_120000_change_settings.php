<?php

namespace putyourlightson\blitz\migrations;

use Craft;
use craft\db\Migration;
use putyourlightson\blitz\Blitz;

class m191001_120000_change_settings extends Migration
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $schemaVersion = Craft::$app->projectConfig
            ->get('plugins.blitz.schemaVersion', true);

        if (!version_compare($schemaVersion, '3.0.0', '<')) {
            return true;
        }

        $info = Craft::$app->getPlugins()->getStoredPluginInfo('blitz');
        $settings = $info ? $info['settings'] : [];

        // Prepend `@webroot` to folder path in cache storage
        if ($settings['cacheStorageType'] == 'putyourlightson\blitz\drivers\storage\FileStorage') {
            $folderPath = 'cache/blitz';

            if (!empty($settings['cacheStorageSettings']) && !empty($settings['cacheStorageSettings']['folderPath'])) {
                $folderPath = $settings['cacheStorageSettings']['folderPath'];
            }

            $settings['cacheStorageSettings']['folderPath'] = '@webroot/'.trim($folderPath, '/');
        }

        // Add keys to URI patterns
        $includedUriPatterns = [];
        if (is_array($settings['includedUriPatterns'])) {
            foreach ($settings['includedUriPatterns'] as $includedUriPattern) {
                $includedUriPatterns[] = [
                    'siteId' => $includedUriPattern[1] ?? '',
                    'uriPattern' => $includedUriPattern[0] ?? '',
                ];
            }
        }
        $settings['includedUriPatterns'] = $includedUriPatterns;

        $excludedUriPatterns = [];
        if (is_array($settings['excludedUriPatterns'])) {
            foreach ($settings['excludedUriPatterns'] as $excludedUriPattern) {
                $excludedUriPatterns[] = [
                    'siteId' => $excludedUriPattern[1] ?? '',
                    'uriPattern' => $excludedUriPattern[0] ?? '',
                ];
            }
        }
        $settings['excludedUriPatterns'] = $excludedUriPatterns;

        // Move concurrency setting into cache warmer settings
        if (empty($settings['cacheWarmerSettings']) && !empty($settings['concurrency'])) {
            $settings['cacheWarmerSettings'] = ['concurrency' => $settings['concurrency']];
        }

        // Set value of `deployJobPriority` to that of `warmCacheJobPriority`
        if (!empty($settings['warmCacheJobPriority'])) {
            $settings['deployJobPriority'] = $settings['warmCacheJobPriority'];
        }

        Craft::$app->getPlugins()->savePluginSettings(Blitz::$plugin, $settings);

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
