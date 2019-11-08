<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\drivers\warmers;

use Craft;
use craft\helpers\App;
use craft\web\UrlManager;
use craft\web\UrlRule;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\helpers\CacheWarmerHelper;
use putyourlightson\blitz\models\SiteUriModel;

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
            //$this->warmUrisWithProgress($siteUris, $setProgressHandler);
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

        /**
         * Create simplified Request and UrlManager configs
         * @see vendor/craftcms/cms/src/config/app.web.php
         */
        $requestConfig = App::webRequestConfig();
        $urlManagerConfig = [
            'class' => UrlManager::class,
            'enablePrettyUrl' => true,
            'ruleConfig' => ['class' => UrlRule::class],
        ];

        foreach ($siteUris as $siteUri) {
            // Convert to a SiteUriModel if it is an array
            if (is_array($siteUri)) {
                $siteUri = new SiteUriModel($siteUri);
            }

            $this->_warmUri($siteUri, $requestConfig, $urlManagerConfig);

            $count++;

            if (is_callable($setProgressHandler)) {
                $progressLabel = Craft::t('blitz', $label, ['count' => $count, 'total' => $total]);
                call_user_func($setProgressHandler, $count, $total, $progressLabel);
            }
        }
    }

    // Private Methods
    // =========================================================================

    /**
     * Warms a site URI.
     *
     * @param SiteUriModel $siteUri
     * @param array $requestConfig
     * @param array $urlManagerConfig
     */
    private function _warmUri(SiteUriModel $siteUri, array $requestConfig, array $urlManagerConfig)
    {
        $url = $siteUri->getUrl();

        /**
         * Mock the web server request
         * @see \craft\test\Craft::recreateClient
        */
        $_SERVER = array_merge($_SERVER, [
            'HTTP_HOST' => parse_url($url, PHP_URL_HOST),
            'SERVER_NAME' => parse_url($url, PHP_URL_HOST),
            'HTTPS' => parse_url($url, PHP_URL_SCHEME) === 'https',
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/'.$siteUri->uri,
            'QUERY_STRING' => 'p='.$siteUri->uri,
        ]);
        $_GET = array_merge($_GET, [
            'p' => $siteUri->uri,
        ]);

        // Recreate the Request and UrlManager components
        Craft::$app->set('request', $requestConfig);
        Craft::$app->set('urlManager', $urlManagerConfig);

        /**
         * Override the host info as it can be set unreliably
         * @see \yii\web\Request::getHostInfo
         */
        Craft::$app->getRequest()->setHostInfo(
            parse_url($url, PHP_URL_SCHEME).'://'
            .parse_url($url, PHP_URL_HOST)
        );

        // Set the template mode to front-end site
        Craft::$app->getView()->setTemplateMode('site');

        // Tell Blitz to process if a cacheable request and not to output the result
        Blitz::$plugin->processCacheableRequest(false);

        // Handle the request with before/after events
        Craft::$app->trigger(Craft::$app::EVENT_BEFORE_REQUEST);
        Craft::$app->handleRequest(Craft::$app->getRequest());
        Craft::$app->trigger(Craft::$app::EVENT_AFTER_REQUEST);
    }
}
