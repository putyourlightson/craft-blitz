<?php

namespace putyourlightson\blitz\migrations;

use Craft;
use craft\db\Migration;
use putyourlightson\blitz\Blitz;

class m191001_120000_changesettings extends Migration
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

        if (empty($settings['cacheWarmerSettings']) && !empty($settings['concurrency'])) {
            $settings['cacheWarmerSettings'] = ['concurrency' => $settings['concurrency']];
        }

        if (!empty($settings['cachePurgerType'])) {
            $settings['purgerType'] = $settings['cachePurgerType'];
        }

        if (!empty($settings['cachePurgerSettings'])) {
            $settings['purgerSettings'] = $settings['cachePurgerSettings'];
        }

        if (!empty($settings['cachePurgerTypes'])) {
            $settings['purgerTypes'] = $settings['cachePurgerTypes'];
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
