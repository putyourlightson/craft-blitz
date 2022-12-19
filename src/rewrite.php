<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

/**
 * Blitz rewrite.php
 *
 * Rewrites a request to a cached file, if it exists, based on the URI.
 * It is useful only in situations where a server rewrite is not possible.
 *
 * Use it by requiring the file and then calling the method, inside the
 * `web/index.php` file, directly after the `bootstrap.php` is required.
 *
 * ```php
 * require CRAFT_VENDOR_PATH . '/putyourlightson/craft-blitz/src/rewrite.php';
 * blitzRewrite();
 * ```
 *
 * @param array{
 *              withQueryString: bool,
 *              cacheFolderPath: string,
 *              tokenParam: string,
 *              actionParam: string,
 *          } $config An array of optional configuration options.
 */
function blitzRewrite(array $config): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        return;
    }

    $tokenParam = $config['tokenParam'] ?? 'token';
    if (!empty($_GET[$tokenParam])) {
        return;
    }

    $actionParam = $config['actionParam'] ?? 'action';
    if (!empty($_GET[$actionParam])) {
        return;
    }

    $host = str_replace(':', '', $_SERVER['HTTP_HOST']);

    $uri = $_SERVER['REQUEST_URI'];
    $withQueryString = $config['withQueryString'] ?? true;
    if ($withQueryString) {
        $uri = str_replace('?', '/', $uri);
    }
    else {
        $uri = explode('?', $uri)[0];
    }

    $cacheFolderPath = $config['cacheFolderPath'] ?? CRAFT_BASE_PATH . '/web/cache/blitz';
    $path = $cacheFolderPath . '/' . $host . $uri . '/index.html';
    $path = str_replace('//', '/', $path);
    $path = str_replace('..', '', $path);

    if (!file_exists($path)) {
        return;
    }

    echo file_get_contents($path);
    exit();
}
