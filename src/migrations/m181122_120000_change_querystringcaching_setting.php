<?php

namespace putyourlightson\blitz\migrations;

use Craft;
use craft\db\Migration;
use craft\db\Query;
use craft\helpers\Json;
use craft\records\Plugin;
use putyourlightson\blitz\Blitz;

class m181122_120000_change_querystringcaching_setting extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        // Only for Craft 3.0
        if (!version_compare(Craft::$app->getInfo()->version, '3.1', '<')) {
            return true;
        }

        // Get old setting from database
        $plugin = (new Query())->from(Plugin::tableName())
            ->where(['handle' => 'blitz'])
            ->one();

        if ($plugin === false || empty($plugin['settings'])) {
            return true;
        }

        $pluginSettings = Json::decodeIfJson($plugin['settings']);

        $queryStringCachingEnabled = $pluginSettings['queryStringCachingEnabled'];

        // Update and save settings with new setting
        $settings = Blitz::$plugin->settings;
        $settings->queryStringCaching = $queryStringCachingEnabled ? 1 : 0;

        Craft::$app->getPlugins()->savePluginSettings(Blitz::$plugin, $settings->getAttributes());

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
