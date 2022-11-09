<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\variables;

use Craft;
use craft\helpers\Template;
use craft\helpers\UrlHelper;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\models\CacheOptionsModel;
use putyourlightson\blitz\services\CacheRequestService;
use Twig\Markup;
use yii\web\NotFoundHttpException;
use yii\web\View;

class BlitzVariable
{
    /**
     * @const string
     */
    public const INCLUDE_ACTION = 'blitz/templates/include';

    /**
     * @const string
     */
    public const DYNAMIC_INCLUDE_ACTION = 'blitz/templates/dynamic-include';

    /**
     * @var int
     */
    private int $_injected = 0;

    /**
     * Returns a (cached) included rendered template.
     *
     * @since 4.3.0
     */
    public function include(string $template, array $params = [], $useAjax = false): Markup
    {
        return $this->_includeTemplate($template, CacheRequestService::INCLUDES_FOLDER, self::INCLUDE_ACTION, $params, $useAjax);
    }

    /**
     * Returns a dynamically (rendered) included rendered template.
     *
     * @since 4.3.0
     */
    public function dynamicInclude(string $template, array $params = [], $useAjax = true): Markup
    {
        return $this->_includeTemplate($template, '', self::DYNAMIC_INCLUDE_ACTION, $params, $useAjax);
    }

    /**
     * Returns a script to fetch the output of a URI.
     *
     * @since 4.3.0
     */
    public function fetch(string $uri, array $params = []): Markup
    {
        return $this->_getScript($uri, $params);
    }

    /**
     * Returns a script to get the output of a template.
     *
     * @deprecated in 4.3.0. Use [[include()]] or [[dynamicInclude()]] instead.
     */
    public function getTemplate(string $template, array $params = []): Markup
    {
        Craft::$app->getDeprecator()->log(__METHOD__, '`craft.blitz.getTemplate()` has been deprecated. Use `craft.blitz.include()` or `craft.blitz.dynamicInclude()` instead.');

        return $this->_includeTemplate($template, '', 'blitz/templates/get', $params, true);
    }

    /**
     * Returns a script to get the output of a URI.
     *
     * @deprecated in 4.3.0. Use [[fetch()]] instead.
     */
    public function getUri(string $uri, array $params = []): Markup
    {
        Craft::$app->getDeprecator()->log(__METHOD__, '`craft.blitz.getUri()` has been deprecated. Use `craft.blitz.fetch()` instead.');

        $params['no-cache'] = 1;

        return $this->_getScript($uri, $params);
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
    private function _includeTemplate(string $template, string $uriPrefix, string $action, array $params = [], bool $useAjax = false): Markup
    {
        if (!Craft::$app->getView()->resolveTemplate($template)) {
            throw new NotFoundHttpException('Template not found: ' . $template);
        }

        // Create a URI relative to the root domain, to account for subfolders
        $uri = parse_url(UrlHelper::siteUrl($uriPrefix), PHP_URL_PATH);

        $params = [
            'action' => $action,
            'template' => $this->_getHashedTemplate($template),
            'params' => $params,
            'siteId' => Craft::$app->getSites()->getCurrentSite()->id,
        ];

        if ($useAjax === false) {
            if (Blitz::$plugin->settings->ssiEnabled) {
                return $this->_getSsiTag($uri, $params);
            }
            if (Blitz::$plugin->settings->esiEnabled) {
                return $this->_getEsiTag($uri, $params);
            }
        }

        return $this->_getScript($uri, $params);
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

        // Add an SSI include, so we can purge it whenever necessary
        if (Blitz::$plugin->cacheRequest->getIsInclude($uri)) {
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

        return Template::raw('<esi:include src="' . $uri . '" />');
    }

    private function _getUriWithParams(string $uri, array $params): string
    {
        return $uri . '?' . http_build_query($params);
    }

    /**
     * Returns a script to inject the output of a URI.
     */
    private function _getScript(string $uri, array $params = [], string $property = null): Markup
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
            'id' => $id,
            'uri' => $uri,
            'params' => http_build_query($params),
            'property' => $property,
        ];

        foreach ($data as $key => &$value) {
            $value = 'data-blitz-' . $key . '="' . $value . '"';
        }

        $output = '<span class="blitz-inject" id="blitz-inject-' . $id . '" ' . implode(' ', $data) . '></span>';

        return Template::raw($output);
    }

    /**
     * Returns a script to inject the output of a CSRF property.
     */
    private function _getCsrfScript(string $property): Markup
    {
        $uri = UrlHelper::actionUrl('blitz/csrf/json');

        return $this->_getScript($uri, [], $property);
    }
}
