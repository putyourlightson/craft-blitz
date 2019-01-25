<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\console\controllers;

use Craft;
use craft\helpers\Console;
use putyourlightson\blitz\Blitz;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Performs functions on the Blitz cache.
 */
class CacheController extends Controller
{
    // Public Methods
    // =========================================================================

    /**
     * Clears the cache (pages only).
     *
     * @return int
     */
    public function actionClear(): int
    {
        Blitz::$plugin->clearService->clearCache(false);

        $this->stdout(Craft::t('blitz', 'Blitz cache successfully cleared.').PHP_EOL, Console::FG_GREEN);

        return ExitCode::OK;
    }

    /**
     * Flushes the cache (pages and database records).
     *
     * @return int
     */
    public function actionFlush(): int
    {
        Blitz::$plugin->clearService->clearCache(true);

        $this->stdout(Craft::t('blitz', 'Blitz cache successfully flushed.').PHP_EOL, Console::FG_GREEN);

        return ExitCode::OK;
    }

    /**
     * Refreshes expired cache.
     *
     * @return int
     */
    public function actionRefreshExpired(): int
    {
        Blitz::$plugin->refreshService->refreshExpiredCache();

        Craft::$app->getQueue()->run();

        $this->stdout(Craft::t('blitz', 'Expired Blitz cache successfully refreshed.').PHP_EOL, Console::FG_GREEN);

        return ExitCode::OK;
    }

    /**
     * Flushes and warms the entire cache.
     *
     * @return int
     */
    public function actionWarm(): int
    {
        if (!Blitz::$plugin->settings->cachingEnabled) {
            $this->stderr(Craft::t('blitz', 'Blitz caching is disabled.').PHP_EOL, Console::FG_RED);

            return ExitCode::OK;
        }

        // Get warm cache URLS
        $urls = Blitz::$plugin->refreshService->getAllCachedSiteUris();

        $this->stdout(Craft::t('blitz', 'Flushing Blitz cache.').PHP_EOL, Console::FG_GREEN);

        Blitz::$plugin->clearService->clearCache(true);

        $this->stdout(Craft::t('blitz', 'Warming Blitz cache.').PHP_EOL, Console::FG_GREEN);

        $total = count($urls);
        Console::startProgress(0, $total, '', 0.8);

        $success = Blitz::$plugin->warmService->requestUrls($urls, [$this, 'setRequestsProgress']);

        Console::updateProgress($total, $total);
        Console::endProgress();

        $this->stdout(Craft::t('blitz', 'Blitz cache successfully warmed {success} files.', ['success' => $success]).PHP_EOL, Console::FG_GREEN);

        return ExitCode::OK;
    }

    /**
     * Sets the request progress.
     *
     * @param int $count
     * @param int $total
     */
    public function setRequestsProgress(int $count, int $total)
    {
        Console::updateProgress($count, $total);
    }
}