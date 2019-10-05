<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\models;

use craft\base\Model;
use craft\helpers\UrlHelper;
use putyourlightson\blitz\Blitz;

/**
 * @property string $url
 * @property bool $isCacheableUri
 */
class SiteUriModel extends Model
{
    // Constants
    // =========================================================================

    /**
     * @const int
     */
    const MAX_URI_LENGTH = 255;

    // Public Properties
    // =========================================================================

    /**
     * @var int
     */
    public $siteId;

    /**
     * @var string
     */
    public $uri;

    // Public Methods
    // =========================================================================

    /**
     * Returns a URL.
     */
    public function getUrl(): string
    {
        return UrlHelper::siteUrl($this->uri, null, null, $this->siteId);
    }

    /**
     * Returns whether the URI is cacheable.
     *
     * @return bool
     */
    public function getIsCacheableUri(): bool
    {
        // Ignore URIs that contain index.php
        if (strpos($this->uri, 'index.php') !== false) {
            return false;
        }

        // Ignore URIs that are longer than the max URI length
        if (strlen($this->uri) > self::MAX_URI_LENGTH) {
            return false;
        }

        // Excluded URI patterns take priority
        if (is_array(Blitz::$plugin->settings->excludedUriPatterns)
            && $this->_matchesUriPatterns(Blitz::$plugin->settings->excludedUriPatterns)) {
            return false;
        }

        if (is_array(Blitz::$plugin->settings->includedUriPatterns)
            && $this->_matchesUriPatterns(Blitz::$plugin->settings->includedUriPatterns)) {
            return true;
        }

        return false;
    }

    // Private Methods
    // =========================================================================

    /**
     * Returns true if the URI matches a set of patterns.
     *
     * @param array $siteUriPatterns
     *
     * @return bool
     */
    private function _matchesUriPatterns(array $siteUriPatterns): bool
    {
        foreach ($siteUriPatterns as $siteUriPattern) {
            // Don't proceed if site is not empty and does not match the provided site ID
            if (!empty($siteUriPattern['siteId']) && $siteUriPattern['siteId'] != $this->siteId) {
                continue;
            }

            $uriPattern = $siteUriPattern['uriPattern'];

            // Replace a blank string with the homepage
            if ($uriPattern == '') {
                $uriPattern = '^$';
            }

            // Replace "*" with 0 or more characters as otherwise it'll throw an error
            if ($uriPattern == '*') {
                $uriPattern = '.*';
            }

            // Trim slashes
            $uriPattern = trim($uriPattern, '/');

            // Escape hash symbols
            $uriPattern = str_replace('#', '\#', $uriPattern);

            if (preg_match('#'.$uriPattern.'#', trim($this->uri, '/'))) {
                return true;
            }
        }

        return false;
    }
}
