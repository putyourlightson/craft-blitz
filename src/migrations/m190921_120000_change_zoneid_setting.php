<?php

namespace putyourlightson\blitz\migrations;

use Craft;
use craft\db\Migration;
use craft\db\Query;
use craft\helpers\Json;
use craft\records\Plugin;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\drivers\purgers\CloudflarePurger;

class m190921_120000_change_zoneid_setting extends Migration
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

        if (!version_compare($schemaVersion, '2.3.0', '<')) {
            //return true;
        }

        $settings = Blitz::$plugin->settings;

        if ($settings->cachePurgerType == CloudflarePurger::class) {
            $primarySite = Craft::$app->getSites()->getPrimarySite();

            if (isset($settings->cachePurgerSettings['zoneId'])) {
                $settings->cachePurgerSettings['zoneIds'][$primarySite->handle]['zoneId'] =
                    $settings->cachePurgerSettings['zoneId'];

                unset($settings->cachePurgerSettings['zoneId']);

                Craft::$app->getPlugins()->savePluginSettings(Blitz::$plugin, $settings->getAttributes());
            }
        }
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
