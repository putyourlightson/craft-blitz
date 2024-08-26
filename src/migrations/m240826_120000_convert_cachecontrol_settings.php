<?php

namespace putyourlightson\blitz\migrations;

use Craft;
use craft\db\Migration;

class m240826_120000_convert_cachecontrol_settings extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $projectConfig = Craft::$app->getProjectConfig();
        $schemaVersion = $projectConfig->get('plugins.blitz.schemaVersion', true);

        if (version_compare($schemaVersion, '4.23.0', '<')) {
            $cacheControlHeader = $projectConfig->get('plugins.blitz.settings.cacheControlHeader') ?? null;
            if ($cacheControlHeader === 'public, max-age=31536000') {
                $projectConfig->set('plugins.blitz.settings.cacheControlHeader', 'public, s-maxage=31536000, max-age=0');
            }

            $cacheControlHeaderExpired = $projectConfig->get('plugins.blitz.settings.cacheControlHeaderExpired') ?? null;
            if ($cacheControlHeaderExpired === 'public, max-age=5') {
                $projectConfig->set('plugins.blitz.settings.cacheControlHeaderExpired', 'public, s-maxage=5, max-age=0');
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
