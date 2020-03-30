<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\console\controllers;

use Craft;
use craft\helpers\Console;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\helpers\SiteUriHelper;
use putyourlightson\blitz\models\SiteUriModel;
use putyourlightson\blitz\utilities\CacheUtility;
use yii\console\Controller;
use yii\console\ExitCode;

class CacheController extends Controller
{
    // Properties
    // =========================================================================

    /**
     * @var array
     */
    private $_actions = [];

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();

        foreach (CacheUtility::getActions(true) as $action) {
            $this->_actions[$action['id']] = $action;
        }

        $this->_actions['generate-expiry-dates'] = [
            'id' => 'generate-expiry-dates',
            'label' => Craft::t('blitz', 'Generate Expiry Dates'),
            'instructions' => Craft::t('blitz', 'Generates and stores entry expiry dates.'),
        ];
    }

    /**
     * @inheritdoc
     */
    public function getHelp(): string
    {
        return 'Blitz actions.';
    }

    /**
     * @inheritdoc
     */
    public function getHelpSummary(): string
    {
        return $this->getHelp();
    }

    /**
     * @inheritdoc
     */
    public function getActionHelp($action): string
    {
        return $this->_actions[$action->id]['instructions'] ?? parent::getActionHelp($action);
    }

    /**
     * @inheritdoc
     */
    public function getActionHelpSummary($action): string
    {
        return $this->getActionHelp($action);
    }

    /**
     * Lists the actions that can be taken.
     *
     * @return int
     */
    public function actionIndex(): int
    {
        $this->stdout(Craft::t('blitz', 'The following actions can be taken:').PHP_EOL.PHP_EOL, Console::FG_YELLOW);

        $lengths = [];
        foreach ($this->_actions as $action) {
            $lengths[] = strlen($action['id']);
        }
        $maxLength = max($lengths);

        foreach ($this->_actions as $action) {
            $this->stdout('- ');
            $this->stdout(str_pad($action['id'], $maxLength, ' '), Console::FG_YELLOW);
            $this->stdout('  '.$action['instructions'].PHP_EOL);
        }

        $this->stdout(PHP_EOL);

        return ExitCode::OK;
    }

    /**
     * @return int
     */
    public function actionClear(): int
    {
        $this->_clearCache();

        return ExitCode::OK;
    }

    /**
     * @return int
     */
    public function actionFlush(): int
    {
        $this->_flushCache();

        return ExitCode::OK;
    }

    /**
     * @return int
     */
    public function actionPurge(): int
    {
        $this->_purgeCache();

        return ExitCode::OK;
    }

    /**
     * @return int
     */
    public function actionWarm(): int
    {
        if (!Blitz::$plugin->settings->cachingEnabled) {
            $this->stderr(Craft::t('blitz', 'Blitz caching is disabled.').PHP_EOL, Console::FG_RED);

            return ExitCode::OK;
        }

        $this->_warmCache(SiteUriHelper::getAllSiteUris(true));

        return ExitCode::OK;
    }

    /**
     * @return int
     */
    public function actionDeploy(): int
    {
        if (!Blitz::$plugin->settings->cachingEnabled) {
            $this->stderr(Craft::t('blitz', 'Blitz caching is disabled.').PHP_EOL, Console::FG_RED);

            return ExitCode::OK;
        }

        $this->_deploy(SiteUriHelper::getAllSiteUris());

        return ExitCode::OK;
    }

    /**
     * @return int
     */
    public function actionRefresh(): int
    {
        // Get cached site URIs before flushing the cache
        $siteUris = SiteUriHelper::getAllSiteUris();

        $this->_clearCache();
        $this->_flushCache();
        $this->_purgeCache();

        if (Blitz::$plugin->settings->cachingEnabled && Blitz::$plugin->settings->warmCacheAutomatically) {
            $warmCacheDelay = Blitz::$plugin->cachePurger->warmCacheDelay;

            if ($warmCacheDelay) {
                $this->stdout(Craft::t('blitz', 'Waiting {seconds} second(s) for the cache to be purged...', ['seconds' => $warmCacheDelay]).PHP_EOL, Console::FG_YELLOW);

                sleep($warmCacheDelay);
            }

            $this->_warmCache($siteUris);
            $this->_deploy($siteUris);
        }

        return ExitCode::OK;
    }

    /**
     * @return int
     */
    public function actionRefreshExpired(): int
    {
        Blitz::$plugin->refreshCache->refreshExpiredCache();

        Craft::$app->getQueue()->run();

        $this->stdout(Craft::t('blitz', 'Expired Blitz cache successfully refreshed.').PHP_EOL, Console::FG_GREEN);

        return ExitCode::OK;
    }

    /**
     * @param array $urls
     *
     * @return int
     */
    public function actionRefreshUrls(array $urls): int
    {
        if (empty($urls)) {
            $this->stderr(Craft::t('blitz', 'One or more URLs must be provided as an argument.').PHP_EOL, Console::FG_RED);

            return ExitCode::OK;
        }

        Blitz::$plugin->refreshCache->refreshCachedUrls($urls);

        Craft::$app->getQueue()->run();

        $this->stdout(Craft::t('blitz', 'Cached URLs successfully refreshed.').PHP_EOL, Console::FG_GREEN);

        return ExitCode::OK;
    }

    /**
     * @param array $tags
     *
     * @return int
     */
    public function actionRefreshTagged(array $tags): int
    {
        if (empty($tags)) {
            $this->stderr(Craft::t('blitz', 'One or more tags must be provided as an argument.').PHP_EOL, Console::FG_RED);

            return ExitCode::OK;
        }

        Blitz::$plugin->refreshCache->refreshCacheTags($tags);

        Craft::$app->getQueue()->run();

        $this->stdout(Craft::t('blitz', 'Tagged cache successfully refreshed.').PHP_EOL, Console::FG_GREEN);

        return ExitCode::OK;
    }

    /**
     * @return int
     */
    public function actionGenerateExpiryDates(): int
    {
        Blitz::$plugin->refreshCache->generateExpiryDates();

        $this->stdout(Craft::t('blitz', 'Entry expiry dates successfully generated.').PHP_EOL, Console::FG_GREEN);

        return ExitCode::OK;
    }

    /**
     * Handles setting the progress.
     *
     * @param int $count
     * @param int $total
     */
    public function setProgressHandler(int $count, int $total)
    {
        Console::updateProgress($count, $total);
    }

    // Private Methods
    // =========================================================================

    private function _clearCache()
    {
        Blitz::$plugin->clearCache->clearAll();

        $this->stdout(Craft::t('blitz', 'Blitz cache successfully cleared.').PHP_EOL, Console::FG_GREEN);
    }

    private function _flushCache()
    {
        Blitz::$plugin->flushCache->flushAll();

        $this->stdout(Craft::t('blitz', 'Blitz cache successfully flushed.').PHP_EOL, Console::FG_GREEN);
    }

    private function _purgeCache()
    {
        Blitz::$plugin->cachePurger->purgeAll();

        $this->stdout(Craft::t('blitz', 'Blitz cache successfully purged.').PHP_EOL, Console::FG_GREEN);
    }

    /**
     * @param SiteUriModel[] $siteUris
     */
    private function _warmCache(array $siteUris)
    {
        $this->stdout(Craft::t('blitz', 'Warming Blitz cache...').PHP_EOL, Console::FG_YELLOW);

        Console::startProgress(0, count($siteUris), '', 0.8);

        Blitz::$plugin->cacheWarmer->warmUris($siteUris, [$this, 'setProgressHandler']);

        Console::endProgress();

        $this->stdout(Craft::t('blitz', 'Blitz cache warming complete.').PHP_EOL, Console::FG_GREEN);
    }

    /**
     * @param SiteUriModel[] $siteUris
     */
    private function _deploy(array $siteUris)
    {
        $this->stdout(Craft::t('blitz', 'Deploying pages...').PHP_EOL, Console::FG_YELLOW);

        Console::startProgress(0, count($siteUris), '', 0.8);

        Blitz::$plugin->deployer->deployUris($siteUris, [$this, 'setProgressHandler']);

        Console::endProgress();

        $this->stdout(Craft::t('blitz', 'Deploying complete.').PHP_EOL, Console::FG_GREEN);
    }
}
