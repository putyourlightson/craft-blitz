<?php

namespace putyourlightson\blitz\migrations;

use Craft;
use craft\db\Migration;
use craft\helpers\Json;
use craft\records\Plugin;
use putyourlightson\blitz\Blitz;

class m181122_120000_change_querystringcaching_setting extends Migration
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        // Get old setting from plugin record
        $pluginRecord = Plugin::find()
            ->where(['handle' => 'blitz'])
            ->one();

        if ($pluginRecord === null) {
            return;
        }

        /** @var Plugin $pluginRecord */
        /** @var string $oldSettingsRaw */
        $oldSettingsRaw = $pluginRecord->settings;
        $pluginSettings = Json::decode($oldSettingsRaw);
        $queryStringCachingEnabled = $pluginSettings['queryStringCachingEnabled'];

        // Update and save settings with new setting
        Blitz::$plugin->settings->queryStringCaching = $queryStringCachingEnabled ? 1 : 0;

        Craft::$app->getPlugins()->savePluginSettings(Blitz::$plugin, Blitz::$plugin->settings->getAttributes());
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
