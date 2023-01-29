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
use putyourlightson\blitz\helpers\SiteUriHelper;
use putyourlightson\blitz\models\CacheOptionsModel;
use putyourlightson\blitz\models\SiteUriModel;
use putyourlightson\blitz\models\VariableConfigModel;
use putyourlightson\blitz\services\CacheRequestService;
use Twig\Markup;
use yii\web\NotFoundHttpException;
use yii\web\View;

class BlitzVariable
{
    /**
     * @const string
     */
    public const STATIC_INCLUDE_ACTION = 'blitz/templates/static-include';

    /**
     * @const string
     */
    public const DYNAMIC_INCLUDE_ACTION = 'blitz/templates/dynamic-include';

    /**
     * @var int
     */
    private int $_injected = 0;

    /**
     * Returns the markup to statically include a template.
     *
     * @since 4.3.0
     */
    public function staticInclude(string $template, array $params = [], array $options = []): Markup
    {
        $config = new VariableConfigModel([
            'requestType' => VariableConfigModel::INCLUDE_REQUEST_TYPE,
        ]);
        $config->setAttributes($options);

        return $this->_includeTemplate($template, CacheRequestService::INCLUDES_FOLDER, self::STATIC_INCLUDE_ACTION, $params, $config);
    }

    /**
     * Returns the markup to dynamically include a template.
     *
     * @since 4.3.0
     */
    public function dynamicInclude(string $template, array $params = [], array $options = []): Markup
    {
        $config = new VariableConfigModel([
            'requestType' => VariableConfigModel::AJAX_REQUEST_TYPE,
        ]);
        $config->setAttributes($options);

        return $this->_includeTemplate($template, '', self::DYNAMIC_INCLUDE_ACTION, $params, $config);
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
     * @deprecated in 4.3.0. Use [[staticInclude()]] or [[dynamicInclude()]] instead.
     */
    public function getTemplate(string $template, array $params = []): Markup
    {
        Craft::$app->getDeprecator()->log(__METHOD__, '`craft.blitz.getTemplate()` has been deprecated. Use `craft.blitz.staticInclude()` or `craft.blitz.dynamicInclude()` instead.');

        $config = new VariableConfigModel();

        return $this->_includeTemplate($template, '', 'blitz/templates/get', $params, $config);
    }

    /**
     * Returns a script to get the output of a URI.
     *
     * @deprecated in 4.3.0. Use [[fetchUri()]] instead.
     */
    public function getUri(string $uri, array $params = []): Markup
    {
        Craft::$app->getDeprecator()->log(__METHOD__, '`craft.blitz.getUri()` has been deprecated. Use `craft.blitz.fetch()` instead.');

        $params['no-cache'] = 1;
        $config = new VariableConfigModel();

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
     * Returns the code to inject the output of a template.
     */
    private function _includeTemplate(string $template, string $uriPrefix, string $action, array $params, VariableConfigModel $config): Markup
    {
        if (!Craft::$app->getView()->resolveTemplate($template)) {
            throw new NotFoundHttpException('Template not found: ' . $template);
        }

        // Create a URI relative to the root domain, to account for sub-folders
        $uri = parse_url(UrlHelper::siteUrl($uriPrefix), PHP_URL_PATH);

        $params = [
            'action' => $action,
            'template' => $this->_getHashedTemplate($template),
            'params' => $params,
            'siteId' => Craft::$app->getSites()->getCurrentSite()->id,
        ];

        if ($config->requestType === VariableConfigModel::INCLUDE_REQUEST_TYPE) {
            if (Blitz::$plugin->settings->ssiEnabled) {
                return $this->_getSsiTag($uri, $params);
            }

            if (Blitz::$plugin->settings->esiEnabled) {
                return $this->_getEsiTag($uri, $params);
            }
        }

        if ($config->requestType === VariableConfigModel::INLINE_REQUEST_TYPE) {
            $content = $this->_getCachedContent($uri, $params);
            if ($content) {
                return $content;
            }
        }

        return $this->_getScript($uri, $params, $config);
    }

    private function _getHashedTemplate(string $template): string
    {
        if (!Craft::$app->getView()->resolveTemplate($template)) {
            throw new NotFoundHttpException('Template not found: ' . $template);
        }

        return Craft::$app->getSecurity()->hashData($template);
    }

    /**
     * Returns an SSI tag to inject the output of a URI.
     */
    private function _getSsiTag(string $uri, array $params = []): Markup
    {
        $uri = $this->_getUriWithParams($uri, $params);

        // Ignore URIs that are longer than the max URI length
        // https://nginx.org/en/docs/http/ngx_http_ssi_module.html#ssi_value_length
        $validUriLength = SiteUriHelper::validateUriLength($uri, 'SSI tag not generated because it exceeds the max URI length of {max} bytes. Consider shortening it by passing in fewer parameters.');

        if ($validUriLength === false) {
            return Template::raw('');
        }

        // Add an SSI include, so we can purge it whenever necessary
        if (Blitz::$plugin->cacheRequest->getIsStaticInclude($uri)) {
            Blitz::$plugin->generateCache->addSsiInclude($uri);
        }

        return Template::raw('<!--#include virtual="' . $uri . '" -->');
    }

    /**
     * Returns an ESI tag to inject the output of a URI.
     */
    private function _getEsiTag(string $uri, array $params = []): Markup
    {
        $uri = $this->_getUriWithParams($uri, $params);

        // Ignore URIs that are longer than the max URI length
        // https://nginx.org/en/docs/http/ngx_http_ssi_module.html#ssi_value_length
        $validUriLength = SiteUriHelper::validateUriLength($uri, 'ESI tag not generated because it exceeds the max URI length of {max} bytes. Consider shortening it by passing in fewer parameters.');

        if ($validUriLength === false) {
            return Template::raw('');
        }

        return Template::raw('<esi:include src="' . $uri . '" />');
    }

    private function _getUriWithParams(string $uri, array $params): string
    {
        return $uri . '?' . http_build_query($params);
    }

    /**
     * Returns the cached content of a URI, or null if it doesn't exist.
     */
    private function _getCachedContent(string $uri, array $params): ?Markup
    {
        $uri = $this->_getUriWithParams($uri, $params);
        $siteUri = new SiteUriModel([
            'siteId' => $params['siteId'],
            'uri' => $uri,
        ]);

        $response = Blitz::$plugin->cacheRequest->getCachedResponse($siteUri);

        return $response ? Template::raw($response->content) : null;
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
        $view->registerJs($js, View::POS_END);

        $this->_injected++;
        $id = $this->_injected;

        $data = [
            'blitz-id' => $id,
            'blitz-uri' => $uri,
            'blitz-params' => http_build_query($params),
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
     * Returns a script to inject the output of a CSRF property.
     */
    private function _getCsrfScript(string $property): Markup
    {
        $uri = UrlHelper::actionUrl('blitz/csrf/json');
        $config = new VariableConfigModel(['property' => $property]);

        return $this->_getScript($uri, [], $config);
    }
}
