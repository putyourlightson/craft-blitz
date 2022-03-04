<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\controllers;

use Craft;
use craft\helpers\App;
use craft\helpers\StringHelper;
use craft\web\Controller;
use craft\web\View;
use putyourlightson\blitz\Blitz;
use yii\web\ForbiddenHttpException;
use yii\web\Response;

class CacheController extends Controller
{
    /**
     * @inheritdoc
     */
    public $enableCsrfValidation = false;

    /**
     * @var bool Disable Snaptcha validation
     */
    public bool $enableSnaptchaValidation = false;

    /**
     * @inheritdoc
     */
    protected array|bool|int $allowAnonymous = true;

    /**
     * @inheritdoc
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
            $apiKey = App::parseEnv(Blitz::$plugin->settings->apiKey);

            if (empty($key) || empty($apiKey) || $key != $apiKey) {
                throw new ForbiddenHttpException('Unauthorised access.');
            }
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function afterAction($action, $result)
    {
        // If front-end request, run the queue to ensure action is completed in full
        if (Craft::$app->getView()->templateMode == View::TEMPLATE_MODE_SITE) {
            Craft::$app->runAction('queue/run');
        }

        return parent::afterAction($action, $result);
    }

    /**
     * Clears the cache.
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
     */
    public function actionRefresh(): Response
    {
        Blitz::$plugin->refreshCache->refreshAll();
        $message = 'Blitz cache successfully refreshed.';

        if (Blitz::$plugin->settings->shouldWarmCache()) {
            $message = 'Blitz cache successfully refreshed and queued for warming.';
        }

        Blitz::$plugin->log($message);

        return $this->_getResponse($message);
    }

    /**
     * Refreshes expired cache.
     */
    public function actionRefreshExpired(): Response
    {
        Blitz::$plugin->refreshCache->refreshExpiredCache();
        $message = 'Expired cache successfully refreshed.';
        Blitz::$plugin->log($message);

        return $this->_getResponse($message);
    }

    /**
     * Refreshes site cache.
     */
    public function actionRefreshSite(): Response
    {
        $siteId = Craft::$app->getRequest()->getParam('siteId');

        if (empty($siteId)) {
            return $this->_getResponse('A site ID must be provided.', false);
        }

        Blitz::$plugin->refreshCache->refreshSite($siteId);
        $message = 'Site successfully refreshed.';

        if (Blitz::$plugin->settings->shouldWarmCache()) {
            $message = 'Site successfully refreshed and queued for warming.';
        }

        Blitz::$plugin->log($message);

        return $this->_getResponse($message);
    }

    /**
     * Refreshes cached URLs.
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

    /**
     * Returns a response.
     */
    private function _getResponse(string $message, bool $success = true): Response
    {
        $request = Craft::$app->getRequest();

        // If front-end or JSON request
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
     * @return string[]
     */
    private function _normalizeArguments(array|string|null $values): array
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
            return array_filter($values);
        }

        return [];
    }
}
