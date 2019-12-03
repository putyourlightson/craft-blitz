<?php

namespace putyourlightson\blitz\migrations;

use Craft;
use craft\db\Migration;
use putyourlightson\blitz\Blitz;

class m191203_120000_change_cachecontrolheader extends Migration
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

        // Update value of `cacheControlHeader` unless it has been customised
        if (empty($settings['cacheControlHeader'])
            || str_replace(' ', '', $settings['cacheControlHeader']) == 'public,s-maxage=0') {
            $settings['cacheControlHeader'] = 'public, s-maxage=31536000, max-age=0';
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
