<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\console\controllers;

use Craft;
use craft\helpers\Console;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\models\SettingsModel;
use yii\console\Controller;

class CacheController extends Controller
{
    // Public Methods
    // =========================================================================

    public function actionClear()
    {
        /** @var SettingsModel $settings */
        $settings = Blitz::$plugin->getSettings();

        if (empty($settings->cacheFolderPath)) {
            $this->stderr(Craft::t('blitz', 'Blitz cache folder path is not set.').PHP_EOL, Console::FG_RED);

            return;
        }

        $this->stdout(Craft::t('blitz', 'Clearing Blitz cache.').PHP_EOL);

        Blitz::$plugin->cache->clearCache();

        $this->stdout(Craft::t('blitz', 'Blitz cache successfully cleared.').PHP_EOL, Console::FG_GREEN);
    }

    public function actionWarm()
    {
        /** @var SettingsModel $settings */
        $settings = Blitz::$plugin->getSettings();

        if (!$settings->cachingEnabled) {
            $this->stderr(Craft::t('blitz', 'Blitz caching is disabled.').PHP_EOL, Console::FG_RED);

            return;
        }

        if (empty($settings->cacheFolderPath)) {
            $this->stderr(Craft::t('blitz', 'Blitz cache folder path is not set.').PHP_EOL, Console::FG_RED);

            return;
        }

        $this->stdout(Craft::t('blitz', 'Warming Blitz cache – this may take some time.').PHP_EOL);

        $count = Blitz::$plugin->cache->warmCache(false);

        $this->stdout(Craft::t('blitz', 'Blitz cache successfully warmed {count} files.', ['count' => $count]).PHP_EOL, Console::FG_GREEN);
    }
}
