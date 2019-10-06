<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\drivers\deployers;

use Craft;
use craft\db\Table;
use craft\helpers\Db;
use craft\helpers\FileHelper;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\events\RefreshCacheEvent;
use putyourlightson\blitz\helpers\DeployerHelper;
use putyourlightson\blitz\helpers\SiteUriHelper;
use Symfony\Component\Process\Process;
use yii\base\ErrorException;
use yii\base\InvalidArgumentException;
use yii\log\Logger;

/**
 * @property mixed $settingsHtml
 */
class GitDeployer extends BaseDeployer
{
    // Properties
    // =========================================================================

    /**
     * @var array
     */
    public $gitSettings = [];

    /**
     * @var string
     */
    public $defaultCommitMessage = 'Blitz auto commit';

    /**
     * @var string
     */
    public $defaultBranch = 'master';

    // Static
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('blitz', 'Git Deployer');
    }

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function deployUris(array $siteUris, int $delay = null, callable $setProgressHandler = null)
    {
        $event = new RefreshCacheEvent(['siteUris' => $siteUris]);
        $this->trigger(self::EVENT_BEFORE_DEPLOY, $event);

        if (!$event->isValid) {
            return;
        }

        if (Craft::$app->getRequest()->getIsConsoleRequest()) {
            $this->deployUrisWithProgress($siteUris, $setProgressHandler);
        }
        else {
            DeployerHelper::addDriverJob(
                $siteUris,
                [$this, 'deployUrisWithProgress'],
                Craft::t('blitz', 'Deploying pages'),
                $delay
            );
        }

        if ($this->hasEventHandlers(self::EVENT_AFTER_DEPLOY)) {
            $this->trigger(self::EVENT_AFTER_DEPLOY, $event);
        }
    }

    /**
     * @inheritdoc
     */
    public function getSettingsHtml()
    {
        return Craft::$app->getView()->renderTemplate('blitz/_drivers/deployers/git/settings', [
            'deployer' => $this,
        ]);
    }

    // Protected Methods
    // =========================================================================

    /**
     * Deploys site URIs with progress.
     *
     * @param array $siteUris
     * @param callable|null $setProgressHandler
     */
    protected function deployUrisWithProgress(array $siteUris, callable $setProgressHandler = null)
    {
        $count = 0;
        $total = 0;
        $label = 'Deploying {count} of {total} pages.';

        $deployGroupedSiteUris = [];
        $groupedSiteUris = SiteUriHelper::getSiteUrisGroupedBySite($siteUris);

        foreach ($groupedSiteUris as $siteId => $siteUris) {
            $siteUid = Db::uidById(Table::SITES, $siteId);

            if ($siteUid === null) {
                continue;
            }

            if (empty($this->gitSettings[$siteUid]) || empty($this->gitSettings[$siteUid]['repositoryPath'])) {
                continue;
            }

            $repositoryPath = FileHelper::normalizePath(
                Craft::parseEnv($this->gitSettings[$siteUid]['repositoryPath'])
            );

            if (FileHelper::isWritable($repositoryPath) === false) {
                continue;
            }

            $deployGroupedSiteUris[$siteUid] = $siteUris;
            $total += count($siteUris);
        }

        if (is_callable($setProgressHandler)) {
            $progressLabel = Craft::t('blitz', $label, ['count' => $count, 'total' => $total]);
            call_user_func($setProgressHandler, $count, $total, $progressLabel);
        }

        foreach ($deployGroupedSiteUris as $siteUid => $siteUris) {
            $repositoryPath = FileHelper::normalizePath(
                Craft::parseEnv($this->gitSettings[$siteUid]['repositoryPath'])
            );

            foreach ($siteUris as $siteUri) {
                $count++;
                $progressLabel = Craft::t('blitz', $label, ['count' => $count, 'total' => $total]);
                call_user_func($setProgressHandler, $count, $total, $progressLabel);

                $value = Blitz::$plugin->cacheStorage->get($siteUri);

                if (empty($value)) {
                    continue;
                }

                $filePath = FileHelper::normalizePath($repositoryPath.'/'.$siteUri->uri.'/index.html');
                $this->save($value, $filePath);
            }

            $commitMessage = $this->gitSettings[$siteUid]['commitMessage'] ?: $this->defaultCommitMessage;
            $commitMessage = addslashes($commitMessage);
            $branch = $this->gitSettings[$siteUid]['branch'] ?: $this->defaultBranch;

            // Run git commands through symfony/process (Git docs: https://devdocs.io/git/)
            $this->runCommands([
                'git checkout '.$branch,
                'git add --all',
                'git commit --message="'.$commitMessage.'"',
                'git push',
            ], $repositoryPath);
        }
    }

    /**
     * Runs an array of commands in a given working directory.
     *
     * @param string[] $commands
     * @param string $cwd
     */
    protected function runCommands(array $commands, string $cwd)
    {
        foreach ($commands as $command) {
            $process = new Process($command, $cwd);
            $process->run();

            if (!$process->isSuccessful()) {
                Craft::getLogger()->log($process->getErrorOutput(), Logger::LEVEL_ERROR, 'blitz');
            }
        }
    }

    /**
     * Saves a value to a file path.
     *
     * @param string $value
     * @param string $filePath
     */
    protected function save(string $value, string $filePath)
    {
        try {
            FileHelper::writeToFile($filePath, $value);
        }
        catch (ErrorException $e) {
            Craft::getLogger()->log($e->getMessage(), Logger::LEVEL_ERROR, 'blitz');
        }
        catch (InvalidArgumentException $e) {
            Craft::getLogger()->log($e->getMessage(), Logger::LEVEL_ERROR, 'blitz');
        }
    }
}
