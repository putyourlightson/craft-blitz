<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\variables;

use Craft;
use craft\helpers\Template;
use craft\helpers\UrlHelper;
use craft\web\View;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\models\CacheOptionsModel;
use Twig\Markup;
use yii\web\NotFoundHttpException;

class BlitzVariable
{
    /**
     * @var int
     */
    private $_injected = 0;

    // Public Methods
    // =========================================================================

    /**
     * Returns script to get the output of a URI.
     *
     * @param string $uri
     * @param array $params
     *
     * @return Markup
     */
    public function getUri(string $uri, array $params = []): Markup
    {
        $params['no-cache'] = 1;

        return $this->_getScript($uri, $params);
    }

    /**
     * Returns script to get the output of a template.
     *
     * @param string $template
     * @param array $params
     *
     * @return Markup
     */
    public function getTemplate(string $template, array $params = []): Markup
    {
        // Ensure template exists
        if (!Craft::$app->getView()->resolveTemplate($template)) {
            throw new NotFoundHttpException('Template not found: '.$template);
        }

        $uri = '/'.Craft::$app->getConfig()->getGeneral()->actionTrigger.'/blitz/templates/get';

        // Hash the template
        $template = Craft::$app->getSecurity()->hashData($template);

        // Add template and passed in params to the params
        $params = [
            'template' => $template,
            'params' => $params,
        ];

        return $this->_getScript($uri, $params);
    }

    /**
     * Returns a script to get a CSRF input field.
     *
     * @return Markup
     */
    public function csrfInput(): Markup
    {
        $uri = '/'.Craft::$app->getConfig()->getGeneral()->actionTrigger.'/blitz/csrf/input';

        return $this->_getScript($uri);
    }

    /**
     * Returns a script to get the CSRF param.
     *
     * @return Markup
     */
    public function csrfParam(): Markup
    {
        $uri = '/'.Craft::$app->getConfig()->getGeneral()->actionTrigger.'/blitz/csrf/param';

        return $this->_getScript($uri);
    }

    /**
     * Returns a script to get a CSRF token.
     *
     * @return Markup
     */
    public function csrfToken(): Markup
    {
        $uri = '/'.Craft::$app->getConfig()->getGeneral()->actionTrigger.'/blitz/csrf/token';

        return $this->_getScript($uri);
    }

    /**
     * Returns options for the current page cache, first setting any parameters provided.
     *
     * @param array $params
     *
     * @return CacheOptionsModel
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
     *
     * @return bool
     */
    public static function getWebAliasExists(): bool
    {
        $sites = Craft::$app->getSites()->getAllSites();

        foreach ($sites as $site) {
            if (strpos($site->baseUrl, '@web') !== false) {
                return true;
            }
        }

        return false;
    }

    // Private Methods
    // =========================================================================

    /**
     * Returns a script to inject the output of a URI into a div.
     *
     * @param string $uri
     * @param array $params
     *
     * @return Markup
     */
    private function _getScript(string $uri, array $params = []): Markup
    {
        $view = Craft::$app->getView();

        if ($this->_injected === 0) {
            $view->registerJs('
                function blitzInject(id, uri) {
                    var customEventInit = {
                        detail: {
                            uri: uri,
                            params: '.json_encode($params).',
                        },
                        cancelable: true,
                    };
                    
                    var xhr = new XMLHttpRequest();
                    xhr.onload = function () {
                        if (xhr.status >= 200 && xhr.status < 300) {
                            var element = document.getElementById("blitz-inject-" + id);
                            if (element) {
                                customEventInit.detail.element = element;
                                customEventInit.detail.responseText = this.responseText;
                                
                                var event = new CustomEvent("beforeBlitzInject", customEventInit);

                                if (!document.dispatchEvent(event)) {
                                    return;
                                }

                                element.innerHTML = this.responseText;

                                var event = new CustomEvent("afterBlitzInject", customEventInit);
                                document.dispatchEvent(event);
                            }
                        }
                    };
                    xhr.open("GET", uri);
                    xhr.setRequestHeader("X-Requested-With", "XMLHttpRequest");
                    xhr.send();
                }
            ', View::POS_END);
        }

        $this->_injected++;

        $id = 'blitz-inject-'.$this->_injected;

        $query = UrlHelper::buildQuery($params);

        if ($query !== '') {
            $uri = $uri.'?'.$query;
        }

        $view->registerJs('blitzInject('.$this->_injected.', "'.$uri.'");', View::POS_END);

        $output = '<span class="blitz-inject" id="'.$id.'"></span>';

        return Template::raw($output);
    }
}
