<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\controllers;

use Craft;
use craft\errors\MissingComponentException;
use craft\web\Controller;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\jobs\WarmCacheJob;
use putyourlightson\blitz\records\CacheRecord;
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
        $siteIds = Craft::$app->getRequest()->getBodyParam('siteIds');

        if (is_array($siteIds)) {
            $cacheIds = [];

            /** @var CacheRecord[] $cacheRecords */
            $cacheRecords = CacheRecord::find()
                ->where(['siteId' => $siteIds])
                ->all();

            foreach ($cacheRecords as $cacheRecord) {
                $cacheIds[] = $cacheRecord->id;

                Blitz::$plugin->file->deleteFileByUri($cacheRecord->siteId, $cacheRecord->uri);
            }

            // Trigger afterRefreshCache event
            Blitz::$plugin->cache->afterRefreshCache($cacheIds);

            // Delete cache records so we get fresh caches
            CacheRecord::deleteAll(['siteId' => $siteIds]);
        }
        else {
            Blitz::$plugin->cache->emptyCache(true);
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

        Blitz::$plugin->cache->emptyCache(true);

        Craft::$app->getQueue()->push(new WarmCacheJob(['urls' => Blitz::$plugin->cache->getAllCacheableUrls()]));

        Craft::$app->getSession()->setNotice(Craft::t('blitz', 'Blitz cache successfully queued for warming.'));

        return $this->redirectToPostedUrl();
    }
}
