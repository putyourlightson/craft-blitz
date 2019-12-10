<?php

namespace putyourlightson\blitz\migrations;

use Craft;
use craft\db\Migration;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\drivers\storage\FileStorage;

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

        $settings = $this->_updateCacheStorage($settings);
        $settings = $this->_updateUriPatterns($settings, 'includedUriPatterns');
        $settings = $this->_updateUriPatterns($settings, 'excludedUriPatterns');

        // Move concurrency setting into cache warmer settings
        if (empty($settings['cacheWarmerSettings']) && !empty($settings['concurrency'])) {
            $settings['cacheWarmerSettings'] = ['concurrency' => $settings['concurrency']];
        }

        // Set value of `deployJobPriority` to that of `warmCacheJobPriority`
        if (!empty($settings['warmCacheJobPriority'])) {
            $settings['deployJobPriority'] = $settings['warmCacheJobPriority'];
        }

        // Set value of `refreshCacheAutomaticallyForGlobals` to that of `clearCacheAutomaticallyForGlobals`
        if (isset($settings['clearCacheAutomaticallyForGlobals'])) {
            $settings['refreshCacheAutomaticallyForGlobals'] = $settings['clearCacheAutomaticallyForGlobals'];
        }

        // Update value of `cacheControlHeader` unless it has been customised
        if (empty($settings['cacheControlHeader'])
            || str_replace(' ', '', $settings['cacheControlHeader']) == 'public,s-maxage=0') {
            $settings['cacheControlHeader'] = 'public, s-maxage=31536000, max-age=0';
        }

        return Craft::$app->getPlugins()->savePluginSettings(Blitz::$plugin, $settings);
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo self::class." cannot be reverted.\n";

        return false;
    }

    // Private Methods
    // =========================================================================

    /**
     * @param array $settings
     *
     * @return array
     */
    private function _updateCacheStorage(array $settings): array
    {
        // Prepend `@webroot` to folder path in cache storage
        if (isset($settings['cacheStorageType']) && $settings['cacheStorageType'] == FileStorage::class) {
            $folderPath = 'cache/blitz';

            if (!empty($settings['cacheStorageSettings']) && !empty($settings['cacheStorageSettings']['folderPath'])) {
                $folderPath = $settings['cacheStorageSettings']['folderPath'];
            }

            $settings['cacheStorageSettings']['folderPath'] = '@webroot/'.trim($folderPath, '/');
        }

        return $settings;
    }

    /**
     * @param array $settings
     * @param string $key
     *
     * @return array
     */
    private function _updateUriPatterns(array $settings, string $key): array
    {
        // Add keys to URI patterns
        if (isset($settings[$key]) && is_array($settings[$key])) {
            $uriPatterns = [];

            foreach ($settings[$key] as $uriPattern) {
                $uriPatterns[] = [
                    'siteId' => $uriPattern[1] ?? '',
                    'uriPattern' => $uriPattern[0] ?? '',
                ];
            }

            $settings[$key] = $uriPatterns;
        }

        return $settings;
    }
}
