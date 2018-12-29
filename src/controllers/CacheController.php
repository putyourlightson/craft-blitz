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
     * Clears the cache.
     *
     * @return Response
     * @throws BadRequestHttpException
     * @throws MissingComponentException
     */
    public function actionClear(): Response
    {
        Blitz::$plugin->cache->emptyCache(false);

        Craft::$app->getSession()->setNotice(Craft::t('blitz', 'Blitz cache successfully cleared.'));

        return $this->redirectToPostedUrl();
    }

    /**
     * Flushes the cache.
     *
     * @return Response
     * @throws BadRequestHttpException
     */
    public function actionFlush(): Response
    {
        Blitz::$plugin->cache->emptyCache(true);

        Craft::$app->getSession()->setNotice(Craft::t('blitz', 'Blitz cache successfully flushed.'));

        return $this->redirectToPostedUrl();
    }

    /**
     * Refreshes expired elements.
     *
     * @return Response
     * @throws BadRequestHttpException
     */
    public function actionRefreshExpired(): Response
    {
        Blitz::$plugin->cache->invalidateCache();

        Craft::$app->getSession()->setNotice(Craft::t('blitz', 'Expired Blitz cache successfully refreshed.'));

        return $this->redirectToPostedUrl();
    }

    /**
     * Warms the cache.
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
