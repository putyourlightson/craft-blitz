<?php
/**
 * Blitz web bootstrap file
 */

use craft\services\Plugins;
use craft\web\View;
use yii\base\Event;

$options = getopt(null, ['url:', 'token:', 'webroot:', 'basePath::']);
$url = $options['url'] ?? null;
$token = $options['token'] ?? null;
$webroot = $options['webroot'] ?? null;
$basePath = $options['basePath'] ?? dirname(__DIR__, 4);

if (empty($url)) {
    exit('No URL provided.' . PHP_EOL);
}

$queryString = parse_url($url, PHP_URL_QUERY);

/**
 * Mock a web server request
 * @see \craft\test\Craft::recreateClient
 */
$_SERVER = array_merge($_SERVER, [
    'SCRIPT_FILENAME' => $webroot . '/index.php',
    'SCRIPT_NAME' => '/index.php',
    'SERVER_NAME' => parse_url($url, PHP_URL_HOST),
    'SERVER_PORT' => parse_url($url, PHP_URL_PORT) ?: '80',
    'HTTPS' => parse_url($url, PHP_URL_SCHEME) === 'https' ? 1 : 0,
    'REQUEST_URI' => parse_url($url, PHP_URL_PATH),
    'QUERY_STRING' => $queryString,
]);

$_GET = [
    'p' => trim(parse_url($url, PHP_URL_PATH), '/'),
];

foreach (explode('&', $queryString) as $queryStringParam) {
    $queryStringParam = explode('=', $queryStringParam);
    if (!empty($queryStringParam)) {
        $_GET[$queryStringParam[0]] = $queryStringParam[1] ?? '';
    }
}

// Load shared bootstrap
require $basePath . '/bootstrap.php';

// Make adjustments before plugins are loaded( as early as possible)
Event::on(Plugins::class, Plugins::EVENT_BEFORE_LOAD_PLUGINS,
    function() use ($webroot) {
        // Force a web request
        Craft::$app->getRequest()->setIsConsoleRequest(false);

        // Set template mode to `site`
        Craft::$app->getView()->setTemplateMode(View::TEMPLATE_MODE_SITE);

        // Set webroot alias
        Craft::$app->setAliases(['@webroot' => $webroot]);
    }
);

// Load the Craft web application
/** @var craft\web\Application $app */
$app = require CRAFT_VENDOR_PATH . '/craftcms/cms/bootstrap/web.php';

// Run Craft
$app->run();
