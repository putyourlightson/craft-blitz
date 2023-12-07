<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\variables;

use Craft;
use craft\helpers\Html;
use craft\helpers\Template;
use craft\helpers\UrlHelper;
use craft\web\View;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\models\CacheOptionsModel;
use putyourlightson\blitz\models\VariableConfigModel;
use putyourlightson\blitz\services\CacheRequestService;
use putyourlightson\blitz\utilities\DiagnosticsUtility;
use Twig\Markup;
use yii\web\NotFoundHttpException;
use yii\web\View as BaseView;

class BlitzVariable
{
    /**
     * Keep this around for backwards compatibility.
     *
     * @todo Remove in version 5.
     * @const string
     */
    public const CACHED_INCLUDE_ACTION = 'blitz/include/cached';

    /**
     * Keep this around for backwards compatibility.
     *
     * @todo Remove in version 5.
     * @const string
     */
    public const DYNAMIC_INCLUDE_ACTION = 'blitz/include/dynamic';

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
     * Returns a script to get the output of a template.
     *
     * @deprecated in 4.3.0. Use [[includeCached()]] or [[includeDynamic()]] instead.
     */
    public function getTemplate(string $template, array $params = []): Markup
    {
        Craft::$app->getDeprecator()->log(__METHOD__, '`craft.blitz.getTemplate()` has been deprecated. Use `craft.blitz.includeCached()` or `craft.blitz.includeDynamic()` instead.');

        // Ensure the site template exists
        if (!Craft::$app->getView()->resolveTemplate($template, View::TEMPLATE_MODE_SITE)) {
            throw new NotFoundHttpException('Template not found: ' . $template);
        }

        $uri = $this->_getActionUrl('blitz/templates/get');

        // Hash the template
        $template = Craft::$app->getSecurity()->hashData($template);

        // Add template and passed in params to the params
        $params = [
            'template' => $template,
            'params' => $params,
            'siteId' => Craft::$app->getSites()->getCurrentSite()->id,
        ];

        $config = new VariableConfigModel();

        return $this->_getScript($uri, $params, $config);
    }

    /**
     * Returns a script to get the output of a URI.
     *
     * @deprecated in 4.3.0. Use [[fetchUri()]] instead.
     */
    public function getUri(string $uri, array $params = []): Markup
    {
        Craft::$app->getDeprecator()->log(__METHOD__, '`craft.blitz.getUri()` has been deprecated. Use `craft.blitz.fetchUri()` instead.');

        $params['no-cache'] = 1;

        return $this->fetchUri($uri, $params);
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
     * Returns a diagnostics utility instance.
     */
    public static function getDiagnostics(): DiagnosticsUtility
    {
        return new DiagnosticsUtility();
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
        // Decode slashes to prevent Apache returning a 404 if `AllowEncodedSlashes` is off.
        // https://github.com/putyourlightson/craft-blitz/issues/564
        return str_replace('%2F', '/', http_build_query($params));
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
