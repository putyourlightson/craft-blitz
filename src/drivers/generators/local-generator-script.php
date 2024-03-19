<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

use Amp\Parallel\Sync\Channel;
use craft\services\Plugins;
use craft\web\View;
use yii\base\Event;

/**
 * This script bootstraps a web app and mocks a web request. It is called by the
 * Local Generator, which runs in a parent process, receiving a channel that is
 * used to send data between the parent and child processes.
 * https://amphp.org/parallel/processes#child-process-or-thread
 *
 * @see putyourlightson\blitz\drivers\generators\LocalGenerator::generateUrisWithProgress()
 */
return function(Channel $channel): Generator {
    $config = yield $channel->receive();

    $url = $config['url'];
    $root = $config['root'];
    $webroot = $config['webroot'];
    $pathParam = $config['pathParam'];

    $https = parse_url($url, PHP_URL_SCHEME) === 'https';
    $queryString = parse_url($url, PHP_URL_QUERY);
    parse_str($queryString, $queryStringParams);

    /**
     * Mock a web server request
     *
     * @see \craft\test\Craft::recreateClient
     */
    $_SERVER = array_merge($_SERVER, [
        'SCRIPT_FILENAME' => $webroot . '/index.php',
        'SCRIPT_NAME' => '/index.php',
        'SERVER_NAME' => parse_url($url, PHP_URL_HOST),
        'SERVER_PORT' => parse_url($url, PHP_URL_PORT) ?: ($https ? '443' : '80'),
        'HTTPS' => $https ? 1 : 0,
        'REQUEST_URI' => parse_url($url, PHP_URL_PATH),
        'QUERY_STRING' => $queryString,
    ]);

    // Merge the path param onto the query string params
    $_GET = array_merge($queryStringParams, [
        $pathParam => trim(parse_url($url, PHP_URL_PATH), '/'),
    ]);

    // We can’t assume the bootstrap file exists.
    // https://github.com/putyourlightson/craft-blitz/issues/404
    if (file_exists($root . '/bootstrap.php')) {
        require_once $root . '/bootstrap.php';
    } else {
        // Based on variations of https://github.com/craftcms/craft/blob/4.x/bootstrap.php
        define('CRAFT_BASE_PATH', $root);
        define('CRAFT_VENDOR_PATH', CRAFT_BASE_PATH . '/vendor');

        /** @noinspection PhpIncludeInspection */
        require_once CRAFT_VENDOR_PATH . '/autoload.php';

        // Load dotenv, depending on the available method and version.
        // https://github.com/vlucas/phpdotenv/blob/master/UPGRADING.md
        if (class_exists(Dotenv\Dotenv::class) && file_exists(CRAFT_BASE_PATH . '/.env')) {
            // Dotenv v5
            if (method_exists('Dotenv\Dotenv', 'createUnsafeMutable')) {
                Dotenv\Dotenv::createUnsafeMutable(CRAFT_BASE_PATH)->safeLoad();
            } // Dotenv v3
            elseif (method_exists('Dotenv\Dotenv', 'create')) {
                /** @noinspection PhpParamsInspection */
                /** @phpstan-ignore-next-line */
                Dotenv\Dotenv::create(CRAFT_BASE_PATH)->load();
            } // Dotenv v2
            else {
                /** @noinspection PhpParamsInspection */
                /** @phpstan-ignore-next-line */
                (new Dotenv\Dotenv(CRAFT_BASE_PATH))->load();
            }
        }
    }

    // Force a web request before plugins are loaded (as early as possible)
    Event::on(Plugins::class, Plugins::EVENT_BEFORE_LOAD_PLUGINS,
        function() {
            Craft::$app->getRequest()->setIsConsoleRequest(false);

            // Force the template mode too
            // https://github.com/putyourlightson/craft-blitz/issues/437
            Craft::$app->getView()->setTemplateMode(View::TEMPLATE_MODE_SITE);
        }
    );

    /**
     * Load and run the Craft web application, checking success based on exit code
     *
     * @see \yii\base\Response::$exitStatus
     * @var craft\web\Application $app
     */
    $app = require $root . '/vendor/craftcms/cms/bootstrap/web.php';

    $success = $app->run() === 0;

    yield $channel->send($success);
};
