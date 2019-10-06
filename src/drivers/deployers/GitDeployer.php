<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\drivers\deployers;

use Craft;
use craft\db\Table;
use craft\helpers\Db;
use craft\helpers\FileHelper;
use GitElephant\Repository;
use GitWrapper\GitWrapper;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\events\RefreshCacheEvent;
use putyourlightson\blitz\helpers\DeployerHelper;
use putyourlightson\blitz\helpers\SiteUriHelper;
use putyourlightson\blitz\models\SiteUriModel;
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
        return Craft::t('blitz', 'Git Repository');
    }

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function deployUris(array $siteUris, int $delay = null)
    {
        if (Craft::$app->getRequest()->getIsConsoleRequest()) {
            $this->commitPushSiteUris($siteUris);

            return;
        }

        $this->addDriverJob($siteUris, $delay);
    }

    /**
     * @inheritdoc
     */
    public function deploySite(int $siteId)
    {
        $this->deployUris(SiteUriHelper::getSiteSiteUris($siteId));
    }

    /**
     * @inheritdoc
     */
    public function callable(array $siteUris, callable $setProgressHandler): int
    {
        $event = new RefreshCacheEvent(['siteUris' => $siteUris]);
        $this->trigger(self::EVENT_BEFORE_DEPLOY, $event);

        if (!$event->isValid) {
            return 0;
        }

        $success = 0;
        $count = 0;
        $total = count($event->siteUris);
        $label = 'Deploying {count} of {total} pages.';

        $progressLabel = Craft::t('blitz', $label, ['count' => $count, 'total' => $total]);
        call_user_func($setProgressHandler, $count, $total, $progressLabel);

        $groupedSiteUris = SiteUriHelper::getSiteUrisGroupedBySite($event->siteUris);

        foreach ($groupedSiteUris as $siteId => $siteUris) {
            $count += count($siteUris);
            $success += $this->deploySiteUris($siteId, $siteUris);

            $progressLabel = Craft::t('blitz', $label, ['count' => $count, 'total' => $total]);
            call_user_func($setProgressHandler, $count, $total, $progressLabel);
        }

        // Fire an 'afterDeploy' event
        if ($this->hasEventHandlers(self::EVENT_AFTER_DEPLOY)) {
            $this->trigger(self::EVENT_AFTER_DEPLOY, $event);
        }

        return $success;
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
     * Adds a driver job to the queue.
     *
     * @param array $siteUris
     * @param null $delay
     */
    protected function addDriverJob(array $siteUris, $delay = null)
    {
        // Add job to queue with a priority and delay
        DeployerHelper::addDriverJob(
            $siteUris,
            [$this, 'callable'],
            Craft::t('blitz', 'Deploying cached files'),
            $delay
        );
    }

    /**
     * Deploys site URIs to the site repository.
     *
     * @param int $siteId
     * @param SiteUriModel[] $siteUris
     *
     * @return int
     */
    protected function deploySiteUris(int $siteId, array $siteUris): int
    {
        $success = 0;

        $siteUid = Db::uidById(Table::SITES, $siteId);

        if ($siteUid === null) {
            return 0;
        }

        if (empty($this->gitSettings[$siteUid]) || empty($this->gitSettings[$siteUid]['repositoryPath'])) {
            return 0;
        }

        $repositoryPath = FileHelper::normalizePath(
            Craft::parseEnv($this->gitSettings[$siteUid]['repositoryPath'])
        );

        if (FileHelper::isWritable($repositoryPath) === false) {
            return 0;
        }

        foreach ($siteUris as $siteUri) {
            $value = Blitz::$plugin->cacheStorage->get($siteUri);

            if (empty($value)) {
                continue;
            }

            $filePath = FileHelper::normalizePath($repositoryPath.'/'.$siteUri->uri.'/index.html');
            $this->save($value, $filePath);

            $success++;
        }

        $gitWrapper = new GitWrapper();
        $gitWrapper->setPrivateKey('~/.ssh/');
        $git = $gitWrapper->workingCopy($repositoryPath);
        $git->add('*');

        if ($git->hasChanges()) {
            // Commit and push repository if there are changes
            $commitMessage = $this->gitSettings[$siteUid]['commitMessage'] ?: $this->defaultCommitMessage;
            $commitMessage = addslashes($commitMessage);
            $branch = $this->gitSettings[$siteUid]['branch'] ?: $this->defaultBranch;

            $git->commit($commitMessage);
            $git->push();
        }

        return $success;
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
            $process->setPty(true);
            $process->setTty(true);
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
