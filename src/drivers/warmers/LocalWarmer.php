<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\drivers\warmers;

use Craft;
use craft\helpers\ArrayHelper;
use Exception;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\helpers\CacheWarmerHelper;
use putyourlightson\blitz\helpers\RequestHelper;
use putyourlightson\blitz\models\SiteUriModel;
use yii\console\Response;

/**
 * @property mixed $settingsHtml
 */
class LocalWarmer extends BaseCacheWarmer
{
    // Static
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('blitz', 'Local Warmer');
    }

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function warmUris(array $siteUris, callable $setProgressHandler = null, int $delay = null)
    {
        if (!$this->beforeWarmCache($siteUris)) {
            return;
        }

        if (Craft::$app->getRequest()->getIsConsoleRequest()) {
            $this->warmUrisWithProgress($siteUris, $setProgressHandler);

            $this->_resetApplicationConfig('console');
        }
        else {
            CacheWarmerHelper::addWarmerJob($siteUris, 'warmUrisWithProgress', $delay);
        }

        $this->afterWarmCache($siteUris);
    }

    /**
     * Warms site URIs with progress.
     *
     * @param array $siteUris
     * @param callable|null $setProgressHandler
     */
    public function warmUrisWithProgress(array $siteUris, callable $setProgressHandler = null, int $delay = null)
    {
        $this->delay($setProgressHandler, $delay);

        $count = 0;
        $total = count($siteUris);
        $label = 'Warming {count} of {total} pages.';

        foreach ($siteUris as $siteUri) {
            $count++;

            if (is_callable($setProgressHandler)) {
                $progressLabel = Craft::t('blitz', $label, ['count' => $count, 'total' => $total]);
                call_user_func($setProgressHandler, $count, $total, $progressLabel);
            }

            // Convert to a SiteUriModel if it is an array
            if (is_array($siteUri)) {
                $siteUri = new SiteUriModel($siteUri);
            }

            $this->_warmUri($siteUri);
        }
    }

    // Private Methods
    // =========================================================================

    /**
     * Warms a site URI.
     *
     * @param SiteUriModel $siteUri
     */
    private function _warmUri(SiteUriModel $siteUri)
    {
        $url = $siteUri->getUrl();

        // Parse the URI rather than getting it from `$siteUri` to ensure we have the full request URI (important!)
        $uri = trim(parse_url($url, PHP_URL_PATH), '/');

        /**
         * Mock the web server request
         * @see \craft\test\Craft::recreateClient
         */
        $_SERVER = array_merge($_SERVER, [
            'HTTP_HOST' => parse_url($url, PHP_URL_HOST),
            'SERVER_NAME' => parse_url($url, PHP_URL_HOST),
            'HTTPS' => parse_url($url, PHP_URL_SCHEME) === 'https',
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/'.$uri,
            'QUERY_STRING' => 'p='.$uri,
        ]);
        $_GET = array_merge($_GET, [
            'p' => $uri,
        ]);
        $_POST = [];
        $_REQUEST = [];

        // Reset the raw body to avoid action params being pulled from it
        //Craft::$app->getRequest()->setRawBody('');

        $this->_resetApplicationConfig('web');

        $request = Craft::$app->getRequest();

        /**
         * Override the host info as it can be set unreliably
         * @see \yii\web\Request::getHostInfo
         */
        $request->setHostInfo(
            parse_url($url, PHP_URL_SCHEME).'://'
            .parse_url($url, PHP_URL_HOST)
        );

        // Set the template mode to front-end site
        Craft::$app->getView()->setTemplateMode('site');

        // Only proceed if this is a cacheable request
        if (!RequestHelper::getIsCacheableRequest() || !$siteUri->getIsCacheableUri()) {
            return;
        }

        // Handle the request with before/after events
        try {
            Craft::$app->trigger(Craft::$app::EVENT_BEFORE_REQUEST);
            $response = Craft::$app->handleRequest($request);
            Craft::$app->trigger(Craft::$app::EVENT_AFTER_REQUEST);

            if ($response->getIsOk()) {
                // Save the cached output
                Blitz::$plugin->generateCache->save($response->data, $siteUri);
            }
            else {

                // Get first line of message only so as not to bloat the logs
                $message = strtok($reason->getMessage(), "\n");

                Blitz::$plugin->log($message, [], 'error');
            }
        }
        catch (Exception $e) {
            Blitz::$plugin->debug($e->getMessage());
        }
    }

    /**
     * Resets the application based on the provided app type (`web` or `console`).
     * @see vendor/craftcms/cms/bootstrap/bootstrap.php
     *
     * @param string $appType
     */
    private function _resetApplicationConfig(string $appType)
    {
        // Merge default app.{appType}.php config with user-defined config
        $config = ArrayHelper::merge(
            require Craft::getAlias('@craft/config/app.'.$appType.'.php'),
            Craft::$app->getConfig()->getConfigFromFile('app.'.$appType)
        );

        // Recreate components from config
        foreach ($config['components'] as $id => $component) {
            // Don't recreate user as it could give errors regarding sessions/cookies
            if ($id == 'user') {
                continue;
            }

            Craft::$app->set($id, $component);
        }

        // If a console request then override the web response with a console response
        if ($appType == 'console') {
            Craft::$app->set('response', Response::class);
        }

        // Set the controller namespace from config
        Craft::$app->controllerNamespace = $config['controllerNamespace'];

        /**
         * Set this explicitly as it may be set by `PHP_SAPI`
         * @see \yii\base\Request::getIsConsoleRequest
         */
        Craft::$app->getRequest()->setIsConsoleRequest(($appType == 'console'));
    }
}
