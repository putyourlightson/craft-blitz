<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\console\controllers;

use Craft;
use craft\helpers\Console;
use craft\helpers\FileHelper;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\models\SettingsModel;
use yii\console\Controller;

class CacheController extends Controller
{
    /**
     * @throws \yii\base\ErrorException
     */
    public function actionClear()
    {
        /** @var SettingsModel $settings */
        $settings = Blitz::$plugin->getSettings();

        if (empty($settings->cacheFolderPath)) {
            $this->stderr('Blitz cache folder path is not set.'.PHP_EOL, Console::FG_RED);

            return;
        }

        FileHelper::removeDirectory(FileHelper::normalizePath(Craft::getAlias('@webroot').'/'.$settings->cacheFolderPath));

        $this->stdout('Blitz cache cleared successfully.'.PHP_EOL, Console::FG_GREEN);
    }
}
