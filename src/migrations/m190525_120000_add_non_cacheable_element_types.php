<?php

namespace putyourlightson\blitz\migrations;

use Craft;
use craft\db\Migration;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\models\SettingsModel;

class m190525_120000_add_non_cacheable_element_types extends Migration
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

        if (!version_compare($schemaVersion, '2.1.0', '<')) {
            return true;
        }

        // Replace nonCacheableElementTypes with new value
        $newSettingsModel = new SettingsModel();
        $newNonCacheableElementTypes = $newSettingsModel->nonCacheableElementTypes;

        $settings = Blitz::$plugin->settings;
        $settings->nonCacheableElementTypes = $newNonCacheableElementTypes;

        Craft::$app->getPlugins()->savePluginSettings(Blitz::$plugin, $settings->getAttributes());
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
