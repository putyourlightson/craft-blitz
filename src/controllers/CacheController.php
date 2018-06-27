<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\controllers;

use Craft;
use craft\helpers\FileHelper;
use craft\web\Controller;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\models\SettingsModel;

class CacheController extends Controller
{
    public function actionClear()
    {
        $this->requirePermission('blitz:clear-cache-utility');

        /** @var SettingsModel $settings */
        $settings = Blitz::$plugin->getSettings();

        if (empty($settings->cacheFolderPath)) {
            return $this->asJson(['success' => true]);
        }

        $cacheFolders = Craft::$app->getRequest()->getRequiredBodyParam('caches');

        if (is_array($cacheFolders)) {
            foreach ($cacheFolders as $cacheFolder) {
                FileHelper::removeDirectory(FileHelper::normalizePath($cacheFolder));
            }
        }
        else {
            FileHelper::removeDirectory(FileHelper::normalizePath(Craft::getAlias('@webroot').'/'.$settings->cacheFolderPath));
        }

        return $this->redirectToPostedUrl();
    }
}
