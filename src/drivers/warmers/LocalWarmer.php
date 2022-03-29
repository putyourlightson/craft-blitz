<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\drivers\warmers;

use Craft;
use craft\controllers\PreviewController;
use craft\helpers\ArrayHelper;
use craft\web\Request;
use craft\web\UrlManager;
use craft\web\UrlRule;
use Exception;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\helpers\CacheWarmerHelper;
use putyourlightson\blitz\models\SiteUriModel;
use yii\console\Response as ConsoleResponse;
use yii\web\Response;
use yii\web\ResponseFormatterInterface;

class LocalWarmer extends BaseCacheWarmer
{
    /**
     * @var mixed
     */
    private mixed $_requestConfig = null;

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('blitz', 'Local Warmer');
    }

    /**
     * @inheritdoc
     */
    public function warmUris(array $siteUris, callable $setProgressHandler = null, bool $queue = true)
    {
        $siteUris = $this->beforeWarmCache($siteUris);

        if (empty($siteUris)) {
            return;
        }

        if ($queue) {
            CacheWarmerHelper::addWarmerJob($siteUris, 'warmUrisWithProgress');
        }
        else {
            $this->warmUrisWithProgress($siteUris, $setProgressHandler);
        }

        $this->afterWarmCache($siteUris);
    }

    /**
     * Warms site URIs with progress.
     */
    public function warmUrisWithProgress(array $siteUris, callable $setProgressHandler = null)
    {
        $isConsoleRequest = Craft::$app->getRequest()->getIsConsoleRequest();

        if ($isConsoleRequest) {
            $this->_configureApplication('web');
        }

        Blitz::$plugin->generateCache->registerElementPrepareEvents();

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

            $success = $this->_warmUri($siteUri);

            if ($success) {
                $this->warmed++;
            }
        }

        // Set back to console request
        if ($isConsoleRequest) {
            $this->_configureApplication('console');
        }
    }

    /**
     * Warms a site URI.
     */
    private function _warmUri(SiteUriModel $siteUri): bool
    {
        // Only proceed if this is a cacheable site URI
        if (!Blitz::$plugin->cacheRequest->getIsCacheableSiteUri($siteUri)) {
            return false;
        }

        // Set the template mode to front-end site
        Craft::$app->getView()->setTemplateMode('site');

        try {
            // Handle the request with before/after events for modules/plugins
            Craft::$app->trigger(Craft::$app::EVENT_BEFORE_REQUEST);

            $request = $this->_createWebRequest($siteUri->getUrl());
            $response = Craft::$app->handleRequest($request, true);

            Craft::$app->trigger(Craft::$app::EVENT_AFTER_REQUEST);

            if (!$response->getIsOk()) {
                Blitz::$plugin->debug($response->data['error'] ?? '', [], $siteUri->getUrl());

                return false;
            }

            $this->_prepareResponse($response);

            if (empty($response->content)) {
                Blitz::$plugin->debug('Response content is empty.', [], $siteUri->getUrl());

                return false;
            }
        }
        catch (Exception $exception) {
            Blitz::$plugin->debug($exception->getMessage(), [], $siteUri->getUrl());

            return false;
        }

        if (Blitz::$plugin->generateCache->save($response->content, $siteUri)) {
            return true;
        }

        return false;
    }

    /**
     * Creates a web request to the provided URL.
     */
    private function _createWebRequest(string $url): Request
    {
        // Parse the URI rather than getting it from `$siteUri` to ensure we have the full request URI (important!)
        $uri = trim(parse_url($url, PHP_URL_PATH), '/');

        /**
         * Mock a web request
         * @see \craft\test\Craft::recreateClient
         */
        $_SERVER = array_merge($_SERVER, [
            'SERVER_NAME' => parse_url($url, PHP_URL_HOST),
            'SERVER_PORT' => parse_url($url, PHP_URL_PORT) ?: '80',
            'HTTPS' => parse_url($url, PHP_URL_SCHEME) === 'https' ? 1 : 0,
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => parse_url($url, PHP_URL_PATH),
            'QUERY_STRING' => parse_url($url, PHP_URL_QUERY),
        ]);

        $_GET = array_merge($_GET, [
            'p' => $uri,
        ]);

        /** @var Request $request */
        $request = Craft::createObject($this->_requestConfig);
        $request->setIsConsoleRequest(false);

        /**
         * Re-route the request.
         * @see PreviewController::actionPreview()
         */
        $urlManager = Craft::$app->getUrlManager();
        $urlManager->setRouteParams([], false);
        $urlManager->setMatchedElement(null);

        return $request;
    }

    /**
     * Configures the existing application using the provided type (`web` or `console`).
     * @see vendor/craftcms/cms/bootstrap/bootstrap.php
     */
    private function _configureApplication(string $type)
    {
        // Add the URL manager component to the config.
        $config = [
            'components' => [
                'urlManager', [
                    'class' => UrlManager::class,
                    'enablePrettyUrl' => true,
                    'ruleConfig' => ['class' => UrlRule::class],
                ],
            ],
        ];

        // Merge in the default app.{type}.php config and user-defined config.
        $config = ArrayHelper::merge(
            $config,
            require Craft::getAlias('@craft/config/app.' . $type . '.php'),
            Craft::$app->getConfig()->getConfigFromFile('app.' . $type)
        );

        // Store the request config for later use.
        $this->_requestConfig = $config['components']['request'] ?? null;

        // Unset the user as recreating it can throw an errors.
        unset($config['components']['user']);

        // Recreate components from config
        foreach ($config['components'] as $id => $component) {
            Craft::$app->set($id, $component);
        }

        /**
         * Set this explicitly as it may be set by `PHP_SAPI`
         * @see \yii\base\Request::getIsConsoleRequest
         */
        Craft::$app->getRequest()->setIsConsoleRequest($type == 'console');

        // Set the controller namespace from config.
        Craft::$app->controllerNamespace = $config['controllerNamespace'];

        if ($type == 'console') {
            // Override the web response with a console response
            Craft::$app->set('response', ConsoleResponse::class);
        }
    }

    /**
     * Prepares the response.
     *
     * @see Response::prepare()
     * @since 2.0.0
     */
    private function _prepareResponse(Response $response)
    {
        if (isset($response->formatters[$response->format])) {
            $formatter = $response->formatters[$response->format];
            if (!is_object($formatter)) {
                $response->formatters[$response->format] = $formatter = Craft::createObject($formatter);
            }
            if ($formatter instanceof ResponseFormatterInterface) {
                $formatter->format($response);
            }
        }
        elseif ($response->format === Response::FORMAT_RAW) {
            if ($response->data !== null) {
                $response->content = $response->data;
            }
        }
    }
}
