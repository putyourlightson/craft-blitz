<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

use Amp\Parallel\Sync\Channel;
use craft\services\Plugins;
use yii\base\Event;

/**
 * This script bootstraps a web app and mocks a web request. It is called by the
 * Local Generator, which runs in a parent process, receiving a channel that is
 * used to send data between the parent and child processes.
 * https://amphp.org/parallel/processes#child-process-or-thread
 *
 * @see putyourlightson\blitz\drivers\generators\LocalGenerator::generateUris()
 */
return function(Channel $channel): Generator {
    $config = yield $channel->receive();

    $url = $config['url'];
    $root = $config['root'];
    $webroot = $config['webroot'];
    $pathParam = $config['pathParam'];

    $queryString = parse_url($url, PHP_URL_QUERY);
    parse_str($queryString, $queryStringParams);

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

    // Merge the path param onto the query string params
    $_GET = array_merge($queryStringParams, [
        $pathParam => trim(parse_url($url, PHP_URL_PATH), '/'),
    ]);

    // Load shared bootstrap
    require $root . '/bootstrap.php';

    // Force a web request before plugins are loaded (as early as possible)
    Event::on(Plugins::class, Plugins::EVENT_BEFORE_LOAD_PLUGINS,
        function() {
            Craft::$app->getRequest()->setIsConsoleRequest(false);
        }
    );

    /**
     * Load and run the Craft web application, checking success based on exit code
     * @see \yii\base\Response::$exitStatus
     * @var craft\web\Application $app
     */
    $app = require $root . '/vendor/craftcms/cms/bootstrap/web.php';
    $success = $app->run() == 0;

    yield $channel->send($success);
};
