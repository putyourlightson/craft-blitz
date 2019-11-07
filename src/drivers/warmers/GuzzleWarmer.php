<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\drivers\warmers;

use Craft;
use craft\helpers\App;
use craft\helpers\UrlHelper;
use craft\web\UrlManager;
use craft\web\UrlRule;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\helpers\CacheWarmerHelper;
use putyourlightson\blitz\models\SiteUriModel;

/**
 * @property mixed $settingsHtml
 */
class GuzzleWarmer extends BaseCacheWarmer
{
    // Properties
    // =========================================================================

    /**
     * @var int
     */
    public $concurrency = 3;

    // Static
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('blitz', 'Guzzle Warmer');
    }

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function warmUri(SiteUriModel $siteUri)
    {
        if (Craft::$app->getRequest()->getIsConsoleRequest()) {
            return;
        }

        $this->_warmUri($siteUri);
    }

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
        $requests = [];

        foreach ($siteUris as $siteUri) {
            // Convert to an array if a SiteUriModel
            if ($siteUri instanceof SiteUriModel) {
                $siteUri = $siteUri->toArray();
            }

            // Get action URL with params
            $actionUrl = UrlHelper::actionUrl('blitz/warmer/warm-site-uri', $siteUri);

            // Remove CP trigger from action  URL
            $actionUrl = str_replace(Craft::$app->getConfig()->getGeneral()->cpTrigger.'/', '', $actionUrl);

            $requests[] = new Request('GET', $actionUrl);
        }

        $count = 0;
        $total = count($siteUris);
        $label = 'Warming {count} of {total} pages.';

        $client = Craft::createGuzzleClient();

        // Create a pool of requests for sending multiple concurrent requests
        $pool = new Pool($client, $requests, [
            'concurrency' => $this->concurrency,
            'fulfilled' => function() use (&$count, $total, $label, $setProgressHandler) {
                $count++;

                if (is_callable($setProgressHandler)) {
                    $progressLabel = Craft::t('blitz', $label, ['count' => $count, 'total' => $total]);
                    call_user_func($setProgressHandler, $count, $total, $progressLabel);
                }
            },
            'rejected' => function($reason) use (&$count, $total, $label, $setProgressHandler) {
                $count++;

                if (is_callable($setProgressHandler)) {
                    $progressLabel = Craft::t('blitz', $label, ['count' => $count, 'total' => $total]);
                    call_user_func($setProgressHandler, $count, $total, $progressLabel);
                }

                if ($reason instanceof RequestException) {
                    /** RequestException $reason */
                    preg_match('/^(.*?)\R/', $reason->getMessage(), $matches);

                    if (!empty($matches[1])) {
                        Blitz::$plugin->log(trim($matches[1], ':'), [], 'error');
                    }
                }
            },
        ]);

        // Initiate the transfers and wait for the pool of requests to complete
        $pool->promise()->wait();
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            [['concurrency'], 'required'],
            [['concurrency'], 'integer', 'min' => 1],
        ];
    }

    /**
     * @inheritdoc
     */
    public function getSettingsHtml()
    {
        return Craft::$app->getView()->renderTemplate('blitz/_drivers/warmers/guzzle/settings', [
            'warmer' => $this,
        ]);
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
        $uri = trim($siteUri->uri, '/');

        /**
         * Mock the web server request
         * @see \craft\test\Craft::recreateClient
        */
        $_SERVER = array_merge($_SERVER, [
            'SERVER_NAME' => parse_url($url, PHP_URL_HOST),
            'SERVER_PORT' => parse_url($url, PHP_URL_PORT) ?: '80',
            'HTTPS' => parse_url($url, PHP_URL_SCHEME) === 'https',
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => $uri,
            'QUERY_STRING' => 'p='.$uri,
        ]);
        $_GET = array_merge($_GET, [
            'p' => $uri,
        ]);

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

        // Recreate the Request and UrlManager components
        Craft::$app->set('request', $requestConfig);
        Craft::$app->set('urlManager', $urlManagerConfig);

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
