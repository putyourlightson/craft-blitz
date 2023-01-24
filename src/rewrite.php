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
 * ```
 *
 * You can configure the rewrite by defining one or more constants. For example,
 * if the `Query String Caching` setting is set to `Cache URLs with query strings
 * as the same page`, then set `BLITZ_INCLUDE_QUERY_STRING` to `false`.
 *
 * ```php
 * // Load Blitz rewrite
 * define('BLITZ_INCLUDE_QUERY_STRING', false);
 * define('BLITZ_CACHE_FOLDER_PATH', 'path/to/cache');
 * require CRAFT_VENDOR_PATH . '/putyourlightson/craft-blitz/src/rewrite.php';
 * ```
 *
 * @since 4.3.0
 */

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    return;
}

$config = require CRAFT_BASE_PATH . '/config/general.php';
if ($config instanceof GeneralConfig) {
    $tokenParam = $config->tokenParam;
} else {
    $tokenParam = $config['tokenParam'] ?? $config[CRAFT_ENVIRONMENT]['tokenParam'] ?? $config['*']['tokenParam'] ?? 'token';
}
if (!empty($_GET[$tokenParam])) {
    return;
}

$tokenParam = defined('BLITZ_TOKEN_PARAM') ? BLITZ_TOKEN_PARAM : 'token';
if (!empty($_GET[$tokenParam])) {
    return;
}

$host = str_replace(':', '', $_SERVER['HTTP_HOST']);
$uri = $_SERVER['REQUEST_URI'];

/** @noinspection PhpTernaryExpressionCanBeReplacedWithConditionInspection */
$includeQueryString = defined('BLITZ_INCLUDE_QUERY_STRING') ? BLITZ_INCLUDE_QUERY_STRING : true;
if ($includeQueryString) {
    $uri = str_replace('?', '/', $uri);
} else {
    $uri = strtok($uri, '?');
}

/**
 * Modify the URI for include action requests.
 * @see CacheRequestService::getRequestedCacheableSiteUri()
 */
$action = $_GET['action'] ?? null;
if ($action === BlitzVariable::STATIC_INCLUDE_ACTION) {
    $uri = CacheRequestService::INCLUDES_FOLDER . '?' . http_build_query($_GET);
} elseif ($action === BlitzVariable::DYNAMIC_INCLUDE_ACTION) {
    $uri = http_build_query($_GET);
}

$cacheFolderPath = defined('BLITZ_CACHE_FOLDER_PATH') ? BLITZ_CACHE_FOLDER_PATH : CRAFT_BASE_PATH . '/web/cache/blitz';
$path = $cacheFolderPath . '/' . $host . $uri . '/index.html';
$path = str_replace(['//', '..'], ['/', ''], $path);

if (!is_file($path)) {
    return;
}

echo file_get_contents($path);
exit();
