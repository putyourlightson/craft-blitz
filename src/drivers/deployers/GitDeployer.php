<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\drivers\deployers;

use Craft;
use craft\behaviors\EnvAttributeParserBehavior;
use craft\db\Table;
use craft\events\CancelableEvent;
use craft\helpers\App;
use craft\helpers\Db;
use craft\helpers\FileHelper;
use Symplify\GitWrapper\Exception\GitException;
use Symplify\GitWrapper\GitWorkingCopy;
use Symplify\GitWrapper\GitWrapper;
use putyourlightson\blitz\Blitz;
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
    /**
     * @event CancelableEvent
     */
    public const EVENT_BEFORE_COMMIT = 'beforeCommit';

    /**
     * @event Event
     */
    public const EVENT_AFTER_COMMIT = 'afterCommit';

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('blitz', 'Git Deployer');
    }

    /**
     * @var array The git repositories to deploy to.
     */
    public array $gitRepositories = [];

    /**
     * @var string The default commit message.
     */
    public string $commitMessage = 'Blitz auto commit';

    /**
     * @var string|null A username for authentication.
     */
    public ?string $username = null;

    /**
     * @var string|null A personal access token for authentication.
     */
    public ?string $personalAccessToken = null;

    /**
     * @var string|null A name.
     */
    public ?string $name = null;

    /**
     * @var string|null An email address.
     */
    public ?string $email = null;

    /**
     * @var string Commands to run before deploying.
     */
    public string $commandsBefore = '';

    /**
     * @var string Commands to run after deploying.
     */
    public string $commandsAfter = '';

    /**
     * @var string The default branch to deploy.
     */
    public string $defaultBranch = 'master';

    /**
     * @var string The default remote to deploy.
     */
    public string $defaultRemote = 'origin';

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
            [['username', 'personalAccessToken', 'name', 'email', 'commitMessage'], 'required'],
            [['email'], 'email'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function deployUrisWithProgress(array $siteUris, callable $setProgressHandler = null)
    {
        $count = 0;
        $total = 0;
        $label = 'Deploying {count} of {total} files.';

        $deployGroupedSiteUris = [];
        $groupedSiteUris = SiteUriHelper::getSiteUrisGroupedBySite($siteUris);

        foreach ($groupedSiteUris as $siteId => $siteUriGroup) {
            if ($this->_hasRepository($siteId)) {
                $deployGroupedSiteUris[$siteId] = $siteUriGroup;
                $total += count($siteUriGroup);
            }
        }

        if (is_callable($setProgressHandler)) {
            $progressLabel = Craft::t('blitz', $label, ['count' => $count, 'total' => $total]);
            call_user_func($setProgressHandler, $count, $total, $progressLabel);
        }

        foreach ($deployGroupedSiteUris as $siteId => $siteUriGroup) {
            $repository = $this->_getRepository($siteId);

            if ($repository === null) {
                continue;
            }

            foreach ($siteUriGroup as $siteUri) {
                $count++;

                if (is_callable($setProgressHandler)) {
                    $progressLabel = Craft::t('blitz', $label, ['count' => $count, 'total' => $total]);
                    call_user_func($setProgressHandler, $count, $total, $progressLabel);
                }

                $filePath = FileHelper::normalizePath($repository['repositoryPath'].'/'.$siteUri->uri.'/index.html');

                $value = Blitz::$plugin->cacheStorage->get($siteUri);

                $this->_updateFile($value, $filePath);
            }

            if (is_callable($setProgressHandler)) {
                $progressLabel = Craft::t('blitz', 'Deploying to remote.');
                call_user_func($setProgressHandler, $count, $total, $progressLabel);
            }

            $this->_deploy($siteId);
        }
    }

    /**
     * @inheritdoc
     */
    public function test(): bool
    {
        foreach ($this->gitRepositories as $siteUid => $gitRepository) {
            $repository = $this->_getRepositoryBySiteUid($siteUid);

            if ($repository === null) {
                continue;
            }

            if (!is_dir($repository['repositoryPath'])) {
                $this->addError('gitRepositories',
                    Craft::t('blitz',
                        'Repository path `{path}` is not a directory.',
                        ['path' => $repository['repositoryPath']]
                    )
                );
                continue;
            }

            if (!FileHelper::isWritable($repository['repositoryPath'])) {
                $this->addError('gitRepositories',
                    Craft::t('blitz',
                        'Repository path `{path}` is not writeable.',
                        ['path' => $repository['repositoryPath']]
                    )
                );
                continue;
            }

            try {
                $git = $this->_getGitWorkingCopy($repository['repositoryPath'], $repository['remote']);

                $git->fetch();
            }
            catch (GitException $e) {
                $this->addError('gitRepositories',
                    Craft::t('blitz',
                        'Error connecting to repository: {error}',
                        ['error' => $e->getMessage()]
                    )
                );
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

        parent::addError($attribute, $error);
    }

    /**
     * Returns the personal access token.
     */
    public function getPersonalAccessToken(): string
    {
        $personalAccessToken = App::parseEnv($this->personalAccessToken);

        if (!is_string($personalAccessToken)) {
            return '';
        }

        return $personalAccessToken;
    }

    /**
     * @inheritdoc
     */
    public function getSettingsHtml(): ?string
    {
        return Craft::$app->getView()->renderTemplate('blitz/_drivers/deployers/git/settings', [
            'deployer' => $this,
        ]);
    }

    /**
     * Returns the repository for a given site UID.
     */
    private function _getRepository(int $siteId): ?array
    {
        $siteUid = Db::uidById(Table::SITES, $siteId);

        if ($siteUid === null) {
            return null;
        }

        return $this->_getRepositoryBySiteUid($siteUid);
    }

    /**
     * Returns the repository path for a given site UID.
     */
    private function _getRepositoryBySiteUid(string $siteUid): ?array
    {
        $repository = $this->gitRepositories[$siteUid] ?? null;

        if (empty($repository)) {
            return null;
        }

        if (empty($repository['repositoryPath'])) {
            return null;
        }

        $repositoryPath = App::parseEnv($repository['repositoryPath']);

        if (!is_string($repositoryPath)) {
            return null;
        }

        $repository['repositoryPath'] = FileHelper::normalizePath($repositoryPath);
        $repository['branch'] = $repository['branch'] ?: $this->defaultBranch;
        $repository['remote'] = $repository['remote'] ?: $this->defaultRemote;

        return $repository;
    }

    /**
     * Returns whether the site has a writeable repository path.
     */
    private function _hasRepository(int $siteId): bool
    {
        $repository = $this->_getRepository($siteId);

        return $repository !== null;
    }

    /**
     * Returns a git working copy.
     */
    private function _getGitWorkingCopy(string $repositoryPath, string $remote): GitWorkingCopy
    {
        $gitCommand = Blitz::$plugin->settings->commands['git'] ?? null;

        if ($gitCommand === null) {
            // Find the git binary (important because `ExecutableFinder` doesn't always find it!)
            $commands = [
                ['type', '-p', 'git'],
                ['which', 'git'],
            ];

            foreach ($commands as $command) {
                $process = new Process($command);
                $process->run();
                $gitCommand = trim($process->getOutput()) ?: null;

                if ($gitCommand !== null) {
                    break;
                }
            }
        }

        $gitWrapper = new GitWrapper($gitCommand);

        // Get working copy
        $git = $gitWrapper->workingCopy($repositoryPath);

        // Set user in config
        $git->config('user.name', $this->name);
        $git->config('user.email', $this->email);

        $remoteUrl = $git->getRemote($remote)['push'];

        // Break the URL into parts and reconstruct with personal access token
        $remoteUrl = (parse_url($remoteUrl, PHP_URL_SCHEME) ?: 'https').'://'
            .$this->username.':'.$this->getPersonalAccessToken().'@'
            .parse_url($remoteUrl, PHP_URL_HOST)
            .parse_url($remoteUrl, PHP_URL_PATH);

        $git->remote('set-url', $remote, $remoteUrl);

        return $git;
    }

    /**
     * Updates a file by saving the value or deleting the file if empty.
     */
    private function _updateFile(string $value, string $filePath)
    {
        if (empty($value)) {
            if (file_exists($filePath)) {
                FileHelper::unlink($filePath);
            }

            return;
        }

        try {
            FileHelper::writeToFile($filePath, $value);
        }
        catch (ErrorException|InvalidArgumentException $exception) {
            Blitz::$plugin->log($exception->getMessage(), [], 'error');
        }
    }

    /**
     * Deploys to the remote repository.
     */
    private function _deploy(int $siteId)
    {
        $event = new CancelableEvent();
        $this->trigger(self::EVENT_BEFORE_COMMIT, $event);

        if (!$event->isValid) {
            return;
        }

        $this->_runCommands($this->commandsBefore);

        $repository = $this->_getRepository($siteId);

        if ($repository === null) {
            return;
        }

        try {
            $git = $this->_getGitWorkingCopy($repository['repositoryPath'], $repository['remote']);

            // Pull down any remote commits
            $git->pull();

            // Add all files to branch and check it out
            $git->add('*');
            $git->checkout($repository['branch']);

            // Check for changes first to avoid an exception being thrown
            if ($git->hasChanges()) {
                // Parse twig tags in the commit message
                $commitMessage = Craft::$app->getView()->renderString($this->commitMessage);

                $git->commit(addslashes($commitMessage));
            }

            $git->push();
        }
        catch (GitException $e) {
            Blitz::$plugin->log('Remote deploy failed: {error}', [
                'error' => $e->getMessage(),
            ], 'error');

            throw $e;
        }

        if ($this->hasEventHandlers(self::EVENT_AFTER_COMMIT)) {
            $this->trigger(self::EVENT_AFTER_COMMIT, new Event());
        }

        $this->_runCommands($this->commandsAfter);
    }

    /**
     * Runs one or more commands.
     *
     * @param string[]|string $commands
     */
    private function _runCommands(array|string $commands)
    {
        if (empty($commands)) {
            return;
        }

        if (is_string($commands)) {
            $commands = preg_split('/\R/', $commands);
        }

        /** @var string $command */
        foreach ($commands as $command) {
            $process = Process::fromShellCommandline($command);
            $process->mustRun();
        }
    }
}
