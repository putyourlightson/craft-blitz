<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\console\controllers;

use Craft;
use craft\helpers\Console;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use putyourlightson\blitz\Blitz;
use yii\console\Controller;

class CacheController extends Controller
{
    // Public Methods
    // =========================================================================

    public function actionClear()
    {
        $settings = Blitz::$plugin->getSettings();

        if (empty($settings->cacheFolderPath)) {
            $this->stderr(Craft::t('blitz', 'Blitz cache folder path is not set.').PHP_EOL, Console::FG_RED);

            return;
        }

        $this->stdout(Craft::t('blitz', 'Clearing Blitz cache.').PHP_EOL);

        Blitz::$plugin->cache->clearFileCache();

        $this->stdout(Craft::t('blitz', 'Blitz cache successfully cleared.').PHP_EOL, Console::FG_GREEN);
    }

    public function actionWarm()
    {
        $settings = Blitz::$plugin->getSettings();

        if (!$settings->cachingEnabled) {
            $this->stderr(Craft::t('blitz', 'Blitz caching is disabled.').PHP_EOL, Console::FG_RED);

            return;
        }

        if (empty($settings->cacheFolderPath)) {
            $this->stderr(Craft::t('blitz', 'Blitz cache folder path is not set.').PHP_EOL, Console::FG_RED);

            return;
        }

        // Prepare and get warm cache URLS
        $urls = Blitz::$plugin->cache->prepareWarmCacheUrls();
        $total = count($urls);
        $count = 0;
        $success = 0;
        $urlErrors = false;
        $client = Craft::createGuzzleClient();
        $requests = [];

        $this->stdout(Craft::t('blitz', 'Warming Blitz cache.').PHP_EOL, Console::FG_GREEN);

        Console::startProgress(0, $total, '', 0.8);

        foreach ($urls as $url) {
            // Ensure URL is an absolute URL starting with http
            if (strpos($url, 'http') === 0) {
                $requests[] = new Request('GET', $url);
            }
            else {
                $urlErrors = true;
                $count++;
                Console::updateProgress($count, $total);
            }
        }

        // Create a pool of requests for sending multiple concurrent requests
        $pool = new Pool($client, $requests, [
            'concurrency' => $settings->concurrency,
            'fulfilled' => function () use (&$success, &$count, $total) {
                $success++;
                $count++;
                Console::updateProgress($count, $total);
            },
            'rejected' => function () use (&$count, $total) {
                $count++;
                Console::updateProgress($count, $total);
            },
        ]);

        // Initiate the transfers and wait for the pool of requests to complete
        $pool->promise()->wait();

        Blitz::$plugin->cache->cleanElementQueryTable();

        Console::updateProgress($total, $total);
        Console::endProgress();

        if ($urlErrors) {
            $this->stdout(Craft::t('blitz', 'One or more URLs do not begin with "http" and were ignored. Please ensure that your site’s base URLs do not use the @web alias.').PHP_EOL, Console::FG_RED);
        }

        $this->stdout(Craft::t('blitz', 'Blitz cache successfully warmed {success} files.', ['success' => $success]).PHP_EOL, Console::FG_GREEN);
    }
}
