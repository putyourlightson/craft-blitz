<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\controllers;

use Craft;
use craft\helpers\StringHelper;
use craft\web\Controller;
use craft\web\View;
use putyourlightson\blitz\Blitz;
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
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $request = Craft::$app->getRequest();

        // Require permission if posted from utility
        if ($request->getIsPost() && $request->getParam('utility')) {
            $this->requirePermission('blitz:'.$action->id);
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

        $message = 'Blitz cache successfully cleared.';
        Blitz::$plugin->log($message);

        return $this->_getResponse($message);
    }

    /**
     * Flushes the cache.
     *
     * @return Response
     */
    public function actionFlush(): Response
    {
        Blitz::$plugin->flushCache->flushAll();

        $message = 'Blitz cache successfully flushed.';
        Blitz::$plugin->log($message);

        return $this->_getResponse($message);
    }

    /**
     * Purges the cache.
     *
     * @return Response
     */
    public function actionPurge(): Response
    {
        Blitz::$plugin->cachePurger->purgeAll();

        $message = 'Blitz cache successfully purged.';
        Blitz::$plugin->log($message);

        return $this->_getResponse($message);
    }

    /**
     * Warms the cache.
     *
     * @return Response
     */
    public function actionWarm(): Response
    {
        if (!Blitz::$plugin->settings->cachingEnabled) {
            return $this->_getResponse('Blitz caching is disabled.', false);
        }

        Blitz::$plugin->cacheWarmer->warmAll();

        $message = 'Blitz cache successfully queued for warming.';
        Blitz::$plugin->log($message);

        return $this->_getResponse($message);
    }

    /**
     * Deploys the cache.
     *
     * @return Response
     */
    public function actionDeploy(): Response
    {
        if (!Blitz::$plugin->settings->cachingEnabled) {
            return $this->_getResponse('Blitz caching is disabled.', false);
        }

        Blitz::$plugin->deployer->deployAll();

        $message = 'Blitz cache successfully queued for deployment.';
        Blitz::$plugin->log($message);

        return $this->_getResponse($message);
    }

    /**
     * Refreshes the entire cache.
     *
     * @return Response
     */
    public function actionRefresh(): Response
    {
        Blitz::$plugin->refreshCache->refreshAll();

        $message = 'Blitz cache successfully refreshed.';

        if (Blitz::$plugin->settings->cachingEnabled && Blitz::$plugin->settings->warmCacheAutomatically) {
            $message = 'Blitz cache successfully refreshed and queued for warming.';
        }

        Blitz::$plugin->log($message);

        return $this->_getResponse($message);
    }

    /**
     * Refreshes expired cache.
     *
     * @return Response
     */
    public function actionRefreshExpired(): Response
    {
        Blitz::$plugin->refreshCache->refreshExpiredCache();

        $message = 'Expired cache successfully refreshed.';
        Blitz::$plugin->log($message);

        return $this->_getResponse($message);
    }

    /**
     * Refreshes cached URLs.
     *
     * @return Response
     */
    public function actionRefreshUrls(): Response
    {
        $urls = Craft::$app->getRequest()->getParam('urls');

        $urls = $this->_normalizeArguments($urls);

        if (empty($urls)) {
            return $this->_getResponse('At least one URL must be provided.', false);
        }

        Blitz::$plugin->refreshCache->refreshCachedUrls($urls);

        $message = 'Cached URLs successfully refreshed.';
        Blitz::$plugin->log($message);

        return $this->_getResponse($message);
    }

    /**
     * Refreshes tagged cache.
     *
     * @return Response
     */
    public function actionRefreshTagged(): Response
    {
        $tags = Craft::$app->getRequest()->getParam('tags');

        $tags = $this->_normalizeArguments($tags);

        if (empty($tags)) {
            return $this->_getResponse('At least one tag must be provided.', false);
        }

        Blitz::$plugin->refreshCache->refreshCacheTags($tags);

        $message = 'Tagged cache successfully refreshed.';
        Blitz::$plugin->log($message);

        return $this->_getResponse($message);
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
    private function _getResponse(string $message, bool $success = true): Response
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

    /**
     * Normalizes values as an array of arguments.
     *
     * @param string|array|null $values
     *
     * @return string[]
     */
    private function _normalizeArguments($values): array
    {
        if (is_string($values)) {
            $values = StringHelper::split($values);
        }

        if (is_array($values)) {
            // Flatten multi-dimensional arrays
            array_walk($values, function(&$value) {
                if (is_array($value)) {
                    $value = reset($value);
                }
            });

            // Remove empty values
            $values = array_filter($values);

            return $values;
        }

        return [];
    }
}
