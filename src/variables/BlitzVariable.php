<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\variables;

use Craft;
use craft\helpers\Html;
use craft\helpers\Template;
use craft\helpers\UrlHelper;
use putyourlightson\blitz\assets\BlitzInjectScriptAsset;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\helpers\DiagnosticsHelper;
use putyourlightson\blitz\models\CacheOptionsModel;
use putyourlightson\blitz\models\VariableConfigModel;
use putyourlightson\blitz\services\CacheRequestService;
use Twig\Markup;
use yii\web\NotFoundHttpException;

class BlitzVariable
{
    /**
     * @const string The default event to use when injecting scripts.
     */
    public const DEFAULT_INJECT_SCRIPT_EVENT = 'DOMContentLoaded';

    /**
     * @var int
     */
    private int $injected = 0;

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

        return $this->includeTemplate($template, CacheRequestService::CACHED_INCLUDE_PREFIX, $params, $config);
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

        return $this->includeTemplate($template, CacheRequestService::DYNAMIC_INCLUDE_PREFIX, $params, $config);
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

        return $this->getScript($uri, $params, $config);
    }

    /**
     * Returns a CSRF input field or a script to inject it.
     */
    public function csrfInput(): Markup
    {
        return $this->getCsrfProperty('input');
    }

    /**
     * Returns a script to get the CSRF param.
     */
    public function csrfParam(): Markup
    {
        return $this->getCsrfProperty('param');
    }

    /**
     * Returns a CSRF token or a script to inject it.
     */
    public function csrfToken(): Markup
    {
        return $this->getCsrfProperty('token');
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

        return $options;
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
    private function includeTemplate(string $template, string $uriPrefix, array $params, VariableConfigModel $config): Markup
    {
        if (!Craft::$app->getView()->resolveTemplate($template)) {
            throw new NotFoundHttpException('Template not found: ' . $template);
        }

        $siteId = Craft::$app->getSites()->getCurrentSite()->id;

        [$includeId, $index] = Blitz::$plugin->generateCache->saveInclude($siteId, $template, $params);

        $includeUri = $uriPrefix . $index;

        // Create a root relative URL to account for sub-folders
        $uri = UrlHelper::rootRelativeUrl(UrlHelper::siteUrl($includeUri));

        if ($config->requestType === VariableConfigModel::INCLUDE_REQUEST_TYPE) {
            if (Blitz::$plugin->cacheRequest->shouldInlineIncludes()) {
                return Template::raw(Craft::$app->getView()->renderTemplate($template, $params));
            }

            // Add the path param as a query string param with the include URI. Since SSI ignores the actual URI, we’ll use the path param to route the request.
            $uri = $uri . '?' . Blitz::$plugin->cacheRequest->cachedIncludePathParam . '=' . $includeUri;

            if (Blitz::$plugin->settings->ssiEnabled) {
                return $this->getSsiTag($uri, $includeId);
            }

            if (Blitz::$plugin->settings->esiEnabled) {
                return $this->getEsiTag($uri);
            }
        }

        return $this->getScript($uri, $params, $config);
    }

    /**
     * Returns an SSI tag to inject the output of a URI.
     */
    private function getSsiTag(string $uri, int $includeId): Markup
    {
        // Add an SSI include, so we can purge it whenever necessary
        Blitz::$plugin->generateCache->addSsiInclude($includeId);

        $ssiTag = Blitz::$plugin->settings->getSsiTag($uri);

        return Template::raw($ssiTag);
    }

    /**
     * Returns an ESI tag to inject the output of a URI.
     */
    private function getEsiTag(string $uri): Markup
    {
        Blitz::$plugin->generateCache->generateData->setHasIncludes();

        // Add surrogate control header
        Craft::$app->getResponse()->getHeaders()->add('Surrogate-Control', 'content="ESI/1.0"');

        return Template::raw('<esi:include src="' . $uri . '" />');
    }

    /**
     * Returns a script to inject the output of a URI.
     */
    private function getScript(string $uri, array $params, VariableConfigModel $config): Markup
    {
        $view = Craft::$app->getView();
        $js = '';

        if ($this->injected === 0) {
            if (Blitz::$plugin->settings->injectScriptEvent === self::DEFAULT_INJECT_SCRIPT_EVENT) {
                $view->registerAssetBundle(BlitzInjectScriptAsset::class, Blitz::$plugin->settings->injectScriptPosition);
            } else {
                $blitzInjectScript = Craft::getAlias('@putyourlightson/blitz/resources/js/blitzInjectScript.js');

                if (file_exists($blitzInjectScript)) {
                    $js = file_get_contents($blitzInjectScript);
                    $js = str_replace(self::DEFAULT_INJECT_SCRIPT_EVENT, Blitz::$plugin->settings->injectScriptEvent, $js);
                }

                $view->registerJs($js, Blitz::$plugin->settings->injectScriptPosition);
            }
        }

        $this->injected++;
        $id = $this->injected;

        // Decode slashes to prevent errors when the `AllowEncodedSlashes` is disabled, only when not using SSI or ESI.
        // https://github.com/putyourlightson/craft-blitz/issues/595
        $queryString = http_build_query($params);
        $queryString = str_replace('%2F', '/', $queryString);

        $data = [
            'blitz-id' => $id,
            'blitz-uri' => $uri,
            'blitz-params' => $queryString,
            'blitz-property' => $config->property,
        ];

        $output = Html::tag($config->wrapperElement, $config->placeholder, [
            'class' => $config->wrapperClass . ' blitz-inject',
            'id' => 'blitz-inject-' . $id,
            'data' => $data,
        ]);

        return Template::raw($output);
    }

    /**
     * Returns a CSRF property or a script to inject it, if this is not an AJAX or Sprig request.
     */
    private function getCsrfProperty(string $property): Markup
    {
        if ($this->getIsAjaxOrSprigRequest()) {
            $value = match ($property) {
                'input' => Html::csrfInput(['async' => false]),
                'param' => Craft::$app->getRequest()->csrfParam,
                'token' => Craft::$app->getRequest()->getCsrfToken(),
                default => '',
            };

            return Template::raw($value);
        }

        $actionUrl = UrlHelper::actionUrl('blitz/csrf/json', null, null, false);
        $uri = UrlHelper::siteUrl(UrlHelper::rootRelativeUrl($actionUrl));
        $config = new VariableConfigModel(['property' => $property]);

        return $this->getScript($uri, [], $config);
    }

    /**
     * Returns whether this is an AJAX or Sprig request.
     */
    private function getIsAjaxOrSprigRequest(): bool
    {
        if (Craft::$app->getRequest()->getIsAjax()) {
            return true;
        }

        $sprigComponentClass = '\putyourlightson\sprig\base\Component';
        if (class_exists($sprigComponentClass, false)) {
            return $sprigComponentClass::isRequest();
        }

        return false;
    }
}
