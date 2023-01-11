<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

use craft\config\GeneralConfig;
use putyourlightson\blitz\services\CacheRequestService;
use putyourlightson\blitz\variables\BlitzVariable;

/**
 * Blitz rewrite.php
 *
 * Rewrites a request to a cached file, if it exists, based on the URI.
 * It is useful only in situations where a server rewrite is not possible.
 *
 * Use it by requiring the file and then calling the method, inside the
 * `web/index.php` file, directly after `bootstrap.php` is required.
 *
 * ```php
 * // Load Blitz rewrite
 * require CRAFT_VENDOR_PATH . '/putyourlightson/craft-blitz/src/rewrite.php';
 * blitzRewrite();
 * ```
 *
 * If the `Query String Caching` setting is set to `Cache URLs with query strings
 * as the same page` then pass in `false` as the first parameter.
 *
 * ```php
 * // Load Blitz rewrite
 * require CRAFT_VENDOR_PATH . '/putyourlightson/craft-blitz/src/rewrite.php';
 * blitzRewrite(false);
 * ```
 *
 * @since 4.3.0
 * @param bool $withQueryString Whether the query string should be included.
 * @param string|null $cacheFolderPath The cache folder path, if different to the default.
 */
function blitzRewrite(bool $withQueryString = true, string $cacheFolderPath = null): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        return;
    }

    $config = require CRAFT_BASE_PATH . '/config/general.php';
    $tokenParam = $config instanceof GeneralConfig ? $config->tokenParam : ($config['tokenParam'] ?? 'token');
    if (!empty($_GET[$tokenParam])) {
        return;
    }

    $host = str_replace(':', '', $_SERVER['HTTP_HOST']);

    $uri = $_SERVER['REQUEST_URI'];
    if ($withQueryString) {
        $uri = str_replace('?', '/', $uri);
    } else {
        $uri = strtok($uri, '?');
    }

    /**
     * Modify the URI for include action requests.
     * @see CacheRequestService::getRequestedCacheableSiteUri()
     */
    $action = $_GET['action'] ?? null;
    if ($action === BlitzVariable::INCLUDE_ACTION) {
        $uri = CacheRequestService::INCLUDES_FOLDER . '?' . http_build_query($_GET);
    } elseif ($action === BlitzVariable::DYNAMIC_INCLUDE_ACTION) {
        $uri = http_build_query($_GET);
    }

    $cacheFolderPath = $cacheFolderPath ?? CRAFT_BASE_PATH . '/web/cache/blitz';
    $path = $cacheFolderPath . '/' . $host . $uri . '/index.html';
    $path = str_replace(['//', '..'], ['/', ''], $path);

    if (!is_file($path)) {
        return;
    }

    echo file_get_contents($path);
    exit();
}
