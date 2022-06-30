<?php

namespace putyourlightson\blitz\migrations;

use Craft;
use craft\db\Migration;
use putyourlightson\blitz\drivers\integrations\CommerceIntegration;

class m220630_120000_add_commerce_integration extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $projectConfig = Craft::$app->getProjectConfig();
        $schemaVersion = $projectConfig->get('plugins.blitz.schemaVersion', true);

        if (version_compare($schemaVersion, '4.2.0', '<')) {
            $integrations = $projectConfig->get('plugins.blitz.settings.integrations') ?? [];
            $integrations = array_merge([CommerceIntegration::class], $integrations);
            $projectConfig->set('plugins.blitz.settings.integrations', $integrations);
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo self::class . " cannot be reverted.\n";

        return false;
    }
}
