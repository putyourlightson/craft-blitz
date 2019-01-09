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
        Blitz::$plugin->cache->clearCache(false);

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
        Blitz::$plugin->cache->clearCache(true);

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
        Blitz::$plugin->cache->refreshExpiredCache();

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

        // Get URLs before flushing the cache
        $urls = Blitz::$plugin->cache->getAllCacheableUrls();

        Blitz::$plugin->cache->clearCache(true);

        Craft::$app->getQueue()->push(new WarmCacheJob(['urls' => $urls]));

        Craft::$app->getSession()->setNotice(Craft::t('blitz', 'Blitz cache successfully queued for warming.'));

        return $this->redirectToPostedUrl();
    }
}
