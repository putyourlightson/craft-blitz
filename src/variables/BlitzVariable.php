<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\variables;

use craft\helpers\Template;
use Twig_Markup;

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
     *
     * @return Twig_Markup
     */
    public function getUri(string $uri): Twig_Markup
    {
        return $this->_getScript($uri);
    }

    /**
     * Returns a script to get a CSRF input field.
     *
     * @return Twig_Markup
     */
    public function csrfInput(): Twig_Markup
    {
        return $this->_getScript('/actions/blitz/csrf/input');
    }

    // Private Methods
    // =========================================================================

    /**
     * Returns a script to inject the output of a URI into a div.
     *
     * @param string $uri
     *
     * @return Twig_Markup
     */
    private function _getScript(string $uri): Twig_Markup
    {
        $output = '';

        if ($this->_injected === 0) {
            $output .= '
                <script>
                function blitzInject(id, uri) {
                    var xhr = new XMLHttpRequest();
                    xhr.onload = function () {
                        if (xhr.status >= 200 && xhr.status < 300) {
                            document.getElementById("blitz-inject-" + id).innerHTML = this.responseText;
                        }
                    };
                    xhr.open("GET", uri);
                    xhr.send();
                }
                </script>
            ';
        }

        $this->_injected++;

        $id = 'blitz-inject-'.$this->_injected;

        $output .= '
            <div id="'.$id.'"></div>
            <script>blitzInject('.$this->_injected.', "'.$uri.'");</script>
        ';

        return Template::raw($output);
    }
}
