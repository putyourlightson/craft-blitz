<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\drivers\deployers;

use Craft;
use craft\behaviors\EnvAttributeParserBehavior;
use craft\db\Table;
use craft\events\CancelableEvent;
use craft\helpers\Db;
use craft\helpers\FileHelper;
use GitWrapper\GitException;
use GitWrapper\GitWorkingCopy;
use GitWrapper\GitWrapper;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\events\RefreshCacheEvent;
use putyourlightson\blitz\helpers\DeployerHelper;
use putyourlightson\blitz\helpers\SiteUriHelper;
use Symfony\Component\Process\Process;
use yii\base\ErrorException;
use yii\base\Event;
use yii\base\InvalidArgumentException;

/**
 * @property mixed $settingsHtml
 */
class GitDeployer extends BaseDeployer
{
    // Constants
    // =========================================================================

    /**
     * @event CancelableEvent
     */
    const EVENT_BEFORE_COMMIT = 'beforeCommit';

    /**
     * @event Event
     */
    const EVENT_AFTER_COMMIT = 'afterCommit';

    // Properties
    // =========================================================================

    /**
     * @var array
     */
    public $gitRepositories = [];

    /**
     * @var string|null
     */
    public $personalAccessToken;

    /**
     * @var string|null
     */
    public $name;

    /**
     * @var string|null
     */
    public $email;

    /**
     * @var string
     */
    public $commitMessage = 'Blitz auto commit';

    /**
     * @var string
     */
    public $defaultBranch = 'master';

    /**
     * @var string
     */
    public $defaultRemote = 'origin';

    /**
     * @var array
     */
    public $commandsBefore = [];

    /**
     * @var array
     */
    public $commandsAfter = [];

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
    public function behaviors(): array
    {
        return [
            'parser' => [
                'class' => EnvAttributeParserBehavior::class,
                'attributes' => ['personalAccessToken'],
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            [['personalAccessToken', 'name', 'email', 'commitMessage'], 'required'],
            [['email'], 'email'],
        ];
    }

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
            DeployerHelper::addDeployerJob($siteUris, 'deployUrisWithProgress', $delay);
        }

        if ($this->hasEventHandlers(self::EVENT_AFTER_DEPLOY)) {
            $this->trigger(self::EVENT_AFTER_DEPLOY, $event);
        }
    }

    /**
     * Deploys site URIs with progress.
     *
     * @param array $siteUris
     * @param callable|null $setProgressHandler
     */
    public function deployUrisWithProgress(array $siteUris, callable $setProgressHandler = null)
    {
        $count = 0;
        $total = 0;
        $label = 'Deploying {count} of {total} files.';

        $deployGroupedSiteUris = [];
        $groupedSiteUris = SiteUriHelper::getSiteUrisGroupedBySite($siteUris);

        foreach ($groupedSiteUris as $siteId => $siteUris) {
            $siteUid = Db::uidById(Table::SITES, $siteId);

            if ($siteUid === null) {
                continue;
            }

            if (empty($this->gitRepositories[$siteUid]) || empty($this->gitRepositories[$siteUid]['repositoryPath'])) {
                continue;
            }

            $repositoryPath = FileHelper::normalizePath(
                Craft::parseEnv($this->gitRepositories[$siteUid]['repositoryPath'])
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
                Craft::parseEnv($this->gitRepositories[$siteUid]['repositoryPath'])
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
                $this->_save($value, $filePath);
            }

            $branch = $this->gitRepositories[$siteUid]['branch'] ?: $this->defaultBranch;
            $remote = $this->gitRepositories[$siteUid]['remote'] ?: $this->defaultRemote;

            $event = new CancelableEvent();
            $this->trigger(self::EVENT_BEFORE_COMMIT, $event);

            if (!$event->isValid) {
                continue;
            }

            foreach ($this->commandsBefore as $command) {
                $process = new Process($command);
                $process->mustRun();
            }

            try {
                // Open repository working copy and add all files to branch
                $gitWrapper = new GitWrapper();
                $git = $gitWrapper->workingCopy($repositoryPath);

                $this->_updateConfig($git, $remote);
                $git->add('*');
                $git->checkout($branch);

                // Check for changes first to avoid an exception being thrown
                if ($git->hasChanges()) {
                    // Parse twig tags in the commit message
                    $commitMessage = Craft::$app->getView()->renderString($this->commitMessage);

                    $git->commit(addslashes($commitMessage));
                }

                $git->push($remote);
            }
            catch (GitException $e) {
                $site = Craft::$app->getSites()->getSiteByUid($siteUid);

                if ($site !== null) {
                    Blitz::$plugin->log('Deploying “{site}” failed: {error}', [
                        'site' => $site->name,
                        'error' => $e->getMessage(),
                    ], 'error');
                }

                throw $e;
            }

            if ($this->hasEventHandlers(self::EVENT_AFTER_COMMIT)) {
                $this->trigger(self::EVENT_AFTER_COMMIT, new Event());
            }

            foreach ($this->commandsAfter as $command) {
                $process = new Process($command);
                $process->mustRun();
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function test(): bool
    {
        foreach ($this->gitRepositories as $siteUid => $gitRepository) {
            $repositoryPath = FileHelper::normalizePath(
                Craft::parseEnv($gitRepository['repositoryPath'])
            );

            if (empty($repositoryPath)) {
                continue;
            }

            $remote = $gitRepository['remote'] ?: $this->defaultRemote;

            try {
                $gitWrapper = new GitWrapper();
                $git = $gitWrapper->workingCopy($repositoryPath);

                $this->_updateConfig($git, $remote);
                $git->fetch($remote);
            }
            catch (GitException $e) {
                $site = Craft::$app->getSites()->getSiteByUid($siteUid);

                if ($site !== null) {
                    $error = Craft::t('blitz', 'Error for “{site}”: {error}', [
                        'site' => $site->name,
                        'error' => $e->getMessage(),
                    ]);

                    // Remove value of personal access token to avoid it being output in plaintext
                    $error = str_replace($this->getPersonalAccessToken(), $this->personalAccessToken, $error);

                    $this->addError('gitRepositories', $error);
                }
            }
        }

        return !$this->hasErrors();
    }

    /**
     * @inheritDoc
     */
    public function addError($attribute, $error = '')
    {
        // Remove value of personal access token to avoid it being output
        $error = str_replace($this->getPersonalAccessToken(), $this->personalAccessToken, $error);

        return parent::addError($attribute, $error);
    }

    /**
     * @return string
     */
    public function getPersonalAccessToken(): string
    {
        return Craft::parseEnv($this->personalAccessToken);
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

    // Private Methods
    // =========================================================================

    /**
     * Updates the config with credentials.
     *
     * @param GitWorkingCopy $git
     * @param string $remote
     */
    private function _updateConfig(GitWorkingCopy $git, string $remote)
    {
        // Set user in config
        $git->config('user.name', $this->name);
        $git->config('user.email', $this->email);

        // Clear output (important!)
        $git->clearOutput();

        $remoteUrl = $git->getRemote($remote)['push'];

        // Break the URL into parts and reconstruct
        $parts = parse_url($remoteUrl);
        $remoteUrl = ($parts['schema'] ?? 'https').'://'
            .$this->getPersonalAccessToken().'@'
            .($parts['host'] ?? '')
            .($parts['path'] ?? '');

        $git->remote('set-url', $remote, $remoteUrl);
    }

    /**
     * Saves a value to a file path.
     *
     * @param string $value
     * @param string $filePath
     */
    private function _save(string $value, string $filePath)
    {
        try {
            FileHelper::writeToFile($filePath, $value);
        }
        catch (ErrorException $e) {
            Blitz::$plugin->log($e->getMessage(), [], 'error');
        }
        catch (InvalidArgumentException $e) {
            Blitz::$plugin->log($e->getMessage(), [], 'error');
        }
    }
}
