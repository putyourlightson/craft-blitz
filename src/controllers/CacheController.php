<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\controllers;

use Craft;
use craft\errors\MissingComponentException;
use craft\helpers\FileHelper;
use craft\web\Controller;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\jobs\WarmCacheJob;
use yii\base\ErrorException;
use yii\web\BadRequestHttpException;
use yii\web\Response;

class CacheController extends Controller
{
    // Public Methods
    // =========================================================================

    /**
     * Clears cache
     *
     * @return Response
     * @throws BadRequestHttpException
     * @throws MissingComponentException
     */
    public function actionClear(): Response
    {
        $settings = Blitz::$plugin->getSettings();

        if (empty($settings->cacheFolderPath)) {
            Craft::$app->getSession()->setError(Craft::t('blitz', 'Blitz cache folder path is not set.'));

            return $this->redirectToPostedUrl();
        }

        $cacheFolders = Craft::$app->getRequest()->getBodyParam('caches');

        if (is_array($cacheFolders)) {
            foreach ($cacheFolders as $cacheFolder) {
                try {
                    FileHelper::removeDirectory(FileHelper::normalizePath($cacheFolder));
                }
                catch (ErrorException $e) {}
            }
        }
        else {
            Blitz::$plugin->file->clearFileCache();
        }

        Craft::$app->getSession()->setNotice(Craft::t('blitz', 'Blitz cache successfully cleared.'));

        return $this->redirectToPostedUrl();
    }

    /**
     * Warms cache
     *
     * @return Response
     * @throws BadRequestHttpException
     * @throws MissingComponentException
     */
    public function actionWarm(): Response
    {
        $settings = Blitz::$plugin->getSettings();

        if (!$settings->cachingEnabled) {
            Craft::$app->getSession()->setError(Craft::t('blitz', 'Blitz caching is disabled.'));

            return $this->redirectToPostedUrl();
        }

        if (empty($settings->cacheFolderPath)) {
            Craft::$app->getSession()->setError(Craft::t('blitz', 'Blitz cache folder path is not set.'));

            return $this->redirectToPostedUrl();
        }

        Craft::$app->getQueue()->push(new WarmCacheJob(['urls' => Blitz::$plugin->cache->prepareWarmCacheUrls()]));

        Craft::$app->getSession()->setNotice(Craft::t('blitz', 'Blitz cache successfully queued for warming.'));

        return $this->redirectToPostedUrl();
    }
}
