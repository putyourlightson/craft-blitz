<?php

namespace putyourlightson\blitz\migrations;

use Craft;
use craft\db\Migration;
use craft\helpers\ProjectConfig as ProjectConfigHelper;

class m240905_120000_convert_enabled_settings extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $projectConfig = Craft::$app->getProjectConfig();

        $settingNames = [
            'includedUriPatterns',
            'excludedUriPatterns',
            'includedQueryStringParams',
            'excludedQueryStringParams',
        ];

        foreach ($settingNames as $settingName) {
            $settings = $projectConfig->get('plugins.blitz.settings.' . $settingName);
            if ($settings !== null) {
                $settings = ProjectConfigHelper::unpackAssociativeArray($settings);
                foreach ($settings as &$setting) {
                    $setting['enabled'] = true;
                }
                $projectConfig->set('plugins.blitz.settings.' . $settingName, $settings);
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
