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
    // Public Properties
    // =========================================================================

    /**
     * @var int|null
     */
    public $siteId;

    /**
     * @var string|null
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

        // Excluded URI patterns take priority
        if (is_array(Blitz::$plugin->settings->excludedUriPatterns)) {
            if ($this->_matchesUriPatterns(Blitz::$plugin->settings->excludedUriPatterns)) {
                return false;
            }
        }

        if (is_array(Blitz::$plugin->settings->includedUriPatterns)) {
            if ($this->_matchesUriPatterns(Blitz::$plugin->settings->includedUriPatterns)) {
                return true;
            }
        }

        return false;
    }

    // Private Methods
    // =========================================================================

    /**
     * Returns true if the URI matches a set of patterns.
     *
     * @param array $patterns
     *
     * @return bool
     */
    private function _matchesUriPatterns(array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            // Don't proceed if site is not empty and does not match the provided site ID
            if (!empty($pattern[1]) && $pattern[1] != $this->siteId) {
                continue;
            }

            $uriPattern = $pattern[0];

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
