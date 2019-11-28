<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\drivers\warmers;

use Craft;
use craft\helpers\App;
use craft\helpers\ArrayHelper;
use craft\web\UrlManager;
use craft\web\UrlRule;
use Exception;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\helpers\CacheWarmerHelper;
use putyourlightson\blitz\models\SiteUriModel;
use Twig\Error\RuntimeError;

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
    public function warmUris(array $siteUris, int $delay = null, callable $setProgressHandler = null)
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
    public function warmUrisWithProgress(array $siteUris, callable $setProgressHandler = null)
    {
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

        // Tell Blitz to process if a cacheable request and not to output the result
        Blitz::$plugin->processCacheableRequest(false);

        // Handle the request with before/after events
        try {
            Craft::$app->trigger(Craft::$app::EVENT_BEFORE_REQUEST);
            Craft::$app->handleRequest($request);
            Craft::$app->trigger(Craft::$app::EVENT_AFTER_REQUEST);
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
        $config = ArrayHelper::merge(
            require Craft::getAlias('@craft/config/app.'.$appType.'.php'),
            Craft::$app->getConfig()->getConfigFromFile('app.'.$appType)
        );

        // Recreate components from config
        foreach ($config['components'] as $id => $component) {
            Craft::$app->set($id, $component);
        }

        Craft::$app->controllerNamespace = $config['controllerNamespace'];

        /**
         * Set this explicitly
         * @see \yii\base\Request::getIsConsoleRequest
         */
        Craft::$app->getRequest()->setIsConsoleRequest(($appType == 'console'));

        // Reset the response data to avoid it being output in the CLI
        Craft::$app->getResponse()->data = '';
    }
}
