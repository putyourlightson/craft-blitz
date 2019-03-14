<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\controllers;

use Craft;
use craft\web\Controller;
use craft\web\View;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\helpers\CacheTagHelper;
use yii\web\ForbiddenHttpException;
use yii\web\Response;

class CacheController extends Controller
{
    // Properties
    // =========================================================================

    /**
     * @inheritdoc
     */
    public $enableCsrfValidation = false;

    /**
     * @var bool Disable Snaptcha validation
     */
    public $enableSnaptchaValidation = false;

    /**
     * @inheritdoc
     */
    protected $allowAnonymous = true;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     *
     * @throws ForbiddenHttpException
     */
    public function beforeAction($action)
    {
        parent::beforeAction($action);

        $request = Craft::$app->getRequest();

        // Require permission if posted from utility
        if ($request->getIsPost() && $request->getParam('utility')) {
            $this->requirePermission('blitz:cache-utility');
        }
        else {
            // Verify API key
            $key = $request->getParam('key');
            $apiKey = Craft::parseEnv(Blitz::$plugin->settings->apiKey);

            if (empty($key) || empty($apiKey) || $key != $apiKey) {
                throw new ForbiddenHttpException('Unauthorised access.');
            }
        }

        return true;
    }

    /**
     * Clears the cache.
     *
     * @return Response
     */
    public function actionClear(): Response
    {
        Blitz::$plugin->clearCache->clearAll();

        return $this->_getResponse('Blitz cache successfully cleared.');
    }

    /**
     * Flushes the cache.
     *
     * @return Response
     */
    public function actionFlush(): Response
    {
        Blitz::$plugin->flushCache->flushAll();

        return $this->_getResponse('Blitz cache successfully flushed.');
    }

    /**
     * Purges the cache.
     *
     * @return Response
     */
    public function actionPurge(): Response
    {
        Blitz::$plugin->cachePurger->purgeAll();

        return $this->_getResponse('Blitz cache successfully purged.');
    }

    /**
     * Warms the cache.
     *
     * @return Response
     */
    public function actionWarm(): Response
    {
        if (!Blitz::$plugin->settings->cachingEnabled) {
            return $this->_getResponse('Blitz caching is disabled.');
        }

        Blitz::$plugin->warmCache->warmAll();

        return $this->_getResponse('Blitz cache successfully queued for warming.');
    }

    /**
     * Refreshes the entire cache.
     *
     * @return Response
     */
    public function actionRefresh(): Response
    {
        if (!Blitz::$plugin->settings->cachingEnabled) {
            return $this->_getResponse('Blitz caching is disabled.');
        }

        Blitz::$plugin->refreshCache->refreshAll();

        return $this->_getResponse('Blitz cache successfully refreshed and queued for warming.');
    }

    /**
     * Refreshes expired cache.
     *
     * @return Response
     */
    public function actionRefreshExpired(): Response
    {
        Blitz::$plugin->refreshCache->refreshExpiredCache();

        return $this->_getResponse('Expired cache successfully refreshed.');
    }

    /**
     * Refreshes tagged cache.
     *
     * @return Response
     */
    public function actionRefreshTagged(): Response
    {
        $tags = Craft::$app->getRequest()->getParam('tags');

        if (empty($tags)) {
            return $this->_getResponse('At least one tag must be provided.', false);
        }

        $tags = CacheTagHelper::getTags($tags);
        Blitz::$plugin->refreshCache->refreshTaggedCache($tags);

        return $this->_getResponse('Tagged cache successfully refreshed.');
    }

    // Private Methods
    // =========================================================================

    /**
     * Returns a response.
     *
     * @param string $message
     * @param bool $success
     *
     * @return Response
     */
    private function _getResponse(string $message, bool $success = true)
    {
        $request = Craft::$app->getRequest();

        // If front-end site or JSON request
        if (Craft::$app->getView()->templateMode == View::TEMPLATE_MODE_SITE || $request->getAcceptsJson()) {
            return $this->asJson([
                'success' => $success,
                'message' => Craft::t('blitz', $message),
            ]);
        }

        if ($success) {
            Craft::$app->getSession()->setNotice(Craft::t('blitz', $message));
        }
        else {
            Craft::$app->getSession()->setError(Craft::t('blitz', $message));
        }

        return $this->redirectToPostedUrl();
    }
}
