<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\console\controllers;

use Craft;
use craft\helpers\Console;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\models\SettingsModel;
use yii\console\Controller;

class CacheController extends Controller
{
    // Public Methods
    // =========================================================================

    public function actionClear()
    {
        /** @var SettingsModel $settings */
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
        /** @var SettingsModel $settings */
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

        $this->stdout(Craft::t('blitz', 'Warming Blitz cache.', ['total' => $total]).PHP_EOL, Console::FG_GREEN);

        Console::startProgress(0, $total, '', 0.8);

        foreach ($urls as $url) {
            // Ensure URL is an absolute URL starting with http
            if (strpos($url, 'http') === 0) {
                try {
                    $response = $client->get($url);
                    $success++;
                }
                catch (ClientException $e) {}
                catch (RequestException $e) {}
            }
            else {
                $urlErrors = true;
            }

            $count++;

            Console::updateProgress($count, $total);
        }

        Console::endProgress();

        if ($urlErrors) {
            $this->stdout(Craft::t('blitz', 'One or more URLs do not begin with "http" and were ignored. Please ensure that your site’s base URLs do not use the @web alias.').PHP_EOL, Console::FG_RED);
        }

        $this->stdout(Craft::t('blitz', 'Blitz cache successfully warmed {success} files.', ['success' => $success]).PHP_EOL, Console::FG_GREEN);
    }
}
