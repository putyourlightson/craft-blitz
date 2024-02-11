<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\variables;

use Craft;
use craft\helpers\Html;
use craft\helpers\Template;
use craft\helpers\UrlHelper;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\helpers\DiagnosticsHelper;
use putyourlightson\blitz\models\CacheOptionsModel;
use putyourlightson\blitz\models\VariableConfigModel;
use putyourlightson\blitz\services\CacheRequestService;
use Twig\Markup;
use yii\web\NotFoundHttpException;
use yii\web\View as BaseView;

class BlitzVariable
{
    /**
     * @var int
     */
    private int $_injected = 0;

    /**
     * Returns the markup to include a cached template.
     *
     * @since 4.3.0
     */
    public function includeCached(string $template, array $params = [], array $options = []): Markup
    {
        $config = new VariableConfigModel([
            'requestType' => VariableConfigModel::INCLUDE_REQUEST_TYPE,
        ]);
        $config->setAttributes($options);

        return $this->_includeTemplate($template, CacheRequestService::CACHED_INCLUDE_PATH, CacheRequestService::CACHED_INCLUDE_ACTION, $params, $config);
    }

    /**
     * Returns the markup to include a dynamically rendered template.
     *
     * @since 4.3.0
     */
    public function includeDynamic(string $template, array $params = [], array $options = []): Markup
    {
        $config = new VariableConfigModel([
            'requestType' => VariableConfigModel::AJAX_REQUEST_TYPE,
        ]);
        $config->setAttributes($options);

        return $this->_includeTemplate($template, CacheRequestService::DYNAMIC_INCLUDE_PATH, CacheRequestService::DYNAMIC_INCLUDE_ACTION, $params, $config);
    }

    /**
     * Returns the markup to fetch the output of a URI.
     *
     * @since 4.3.0
     */
    public function fetchUri(string $uri, array $params = [], array $options = []): Markup
    {
        $config = new VariableConfigModel();
        $config->setAttributes($options);

        return $this->_getScript($uri, $params, $config);
    }

    /**
     * Returns a script to get a CSRF input field.
     */
    public function csrfInput(): Markup
    {
        return $this->_getCsrfScript('input');
    }

    /**
     * Returns a script to get the CSRF param.
     */
    public function csrfParam(): Markup
    {
        return $this->_getCsrfScript('param');
    }

    /**
     * Returns a script to get a CSRF token.
     */
    public function csrfToken(): Markup
    {
        return $this->_getCsrfScript('token');
    }

    /**
     * Returns options for the current page cache, first setting any parameters provided.
     */
    public function options(array $params = []): CacheOptionsModel
    {
        $options = Blitz::$plugin->generateCache->options;

        if (isset($params['cacheDuration'])) {
            $options->cacheDuration($params['cacheDuration']);
        }

        $options->setAttributes($params, false);

        if ($options->validate()) {
            Blitz::$plugin->generateCache->options = $options;
        }

        return Blitz::$plugin->generateCache->options;
    }

    /**
     * Returns whether the `@web` alias is used in any site's base URL.
     */
    public static function getWebAliasExists(): bool
    {
        $sites = Craft::$app->getSites()->getAllSites();

        foreach ($sites as $site) {
            if (str_contains($site->baseUrl, '@web')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns an instance of the diagnostics helper.
     */
    public static function getDiagnostics(): DiagnosticsHelper
    {
        return new DiagnosticsHelper();
    }

    /**
     * Returns the code to inject the output of a template.
     */
    private function _includeTemplate(string $template, string $uriPrefix, string $action, array $params, VariableConfigModel $config): Markup
    {
        if (!Craft::$app->getView()->resolveTemplate($template)) {
            throw new NotFoundHttpException('Template not found: ' . $template);
        }

        $siteId = Craft::$app->getSites()->getCurrentSite()->id;

        [$includeId, $index] = Blitz::$plugin->generateCache->saveInclude($siteId, $template, $params);

        // Create a root relative URL to account for sub-folders
        $uri = UrlHelper::rootRelativeUrl(UrlHelper::siteUrl($uriPrefix));

        $includeParams = [
            'action' => $action,
            'index' => $index,
        ];

        if ($config->requestType === VariableConfigModel::INCLUDE_REQUEST_TYPE) {
            if (Blitz::$plugin->settings->ssiEnabled) {
                return $this->_getSsiTag($uri, $includeParams, $includeId);
            }

            if (Blitz::$plugin->settings->esiEnabled) {
                return $this->_getEsiTag($uri, $includeParams);
            }
        }

        return $this->_getScript($uri, $includeParams, $config);
    }

    /**
     * Returns an SSI tag to inject the output of a URI.
     */
    private function _getSsiTag(string $uri, array $params, int $includeId): Markup
    {
        // Add an SSI include, so we can purge it whenever necessary
        Blitz::$plugin->generateCache->addSsiInclude($includeId);

        $uri = $this->_getUriWithParams($uri, $params);
        $ssiTag = Blitz::$plugin->settings->getSsiTag($uri);

        return Template::raw($ssiTag);
    }

    /**
     * Returns an ESI tag to inject the output of a URI.
     */
    private function _getEsiTag(string $uri, array $params): Markup
    {
        Blitz::$plugin->generateCache->generateData->setHasIncludes();
        $uri = $this->_getUriWithParams($uri, $params);

        // Add surrogate control header
        Craft::$app->getResponse()->getHeaders()->add('Surrogate-Control', 'content="ESI/1.0"');

        return Template::raw('<esi:include src="' . $uri . '" />');
    }

    private function _getUriWithParams(string $uri, array $params): string
    {
        // Get the URL path only
        $uri = parse_url(UrlHelper::siteUrl($uri), PHP_URL_PATH);

        return $uri . '?' . $this->_getQueryString($params);
    }

    private function _getQueryString(array $params): string
    {
        // Remove the path param if it exists.
        $pathParam = Craft::$app->getConfig()->getGeneral()->pathParam;
        if ($pathParam && isset($params[$pathParam])) {
            unset($params[$pathParam]);
        }

        return http_build_query($params);
    }

    /**
     * Returns a script to inject the output of a URI.
     */
    private function _getScript(string $uri, array $params, VariableConfigModel $config): Markup
    {
        $view = Craft::$app->getView();
        $js = '';

        if ($this->_injected === 0) {
            $blitzInjectScript = Craft::getAlias('@putyourlightson/blitz/resources/js/blitzInjectScript.js');

            if (file_exists($blitzInjectScript)) {
                $js = file_get_contents($blitzInjectScript);
                $js = str_replace('{injectScriptEvent}', Blitz::$plugin->settings->injectScriptEvent, $js);
            }
        }

        // Create polyfills using https://polyfill.io/v3/url-builder/.
        $polyfills = ['fetch', 'Promise', 'CustomEvent'];
        $polyfillUrl = 'https://polyfill.io/v3/polyfill.min.js?features=' . implode('%2C', $polyfills);

        // Register polyfills for IE11 only, using the `module/nomodule` pattern.
        // https://3perf.com/blog/polyfills/#modulenomodule
        $view->registerJsFile($polyfillUrl, ['nomodule' => true]);
        $view->registerJs($js, BaseView::POS_END);

        $this->_injected++;
        $id = $this->_injected;

        $data = [
            'blitz-id' => $id,
            'blitz-uri' => $uri,
            'blitz-params' => $this->_getQueryString($params),
            'blitz-property' => $config->property,
        ];

        $output = Html::tag($config->wrapperElement, $config->placeholder, [
            'class' => 'blitz-inject',
            'id' => 'blitz-inject-' . $id,
            'data' => $data,
        ]);

        return Template::raw($output);
    }

    /**
     * Returns an absolute action URL for a URI.
     */
    private function _getActionUrl(string $uri): string
    {
        return UrlHelper::actionUrl($uri, null, null, false);
    }

    /**
     * Returns a script to inject the output of a CSRF property.
     */
    private function _getCsrfScript(string $property): Markup
    {
        $uri = $this->_getActionUrl('blitz/csrf/json');
        $config = new VariableConfigModel(['property' => $property]);

        return $this->_getScript($uri, [], $config);
    }
}
