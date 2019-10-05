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

        if (empty($settings['cacheWarmerSettings']) && !empty($settings['concurrency'])) {
            $settings['cacheWarmerSettings'] = ['concurrency' => $settings['concurrency']];
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
