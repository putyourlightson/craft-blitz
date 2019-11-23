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
    public $username;

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
     * @var string
     */
    public $commandsBefore = '';

    /**
     * @var string
     */
    public $commandsAfter = '';

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
            [['username', 'personalAccessToken', 'name', 'email', 'commitMessage'], 'required'],
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

            $repositoryPath = $this->_getRepositoryPath($siteUid);

            if ($repositoryPath === null) {
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
            $repositoryPath = $this->_getRepositoryPath($siteUid);

            if ($repositoryPath === null) {
                continue;
            }

            foreach ($siteUris as $siteUri) {
                $count++;

                if (is_callable($setProgressHandler)) {
                    $progressLabel = Craft::t('blitz', $label, ['count' => $count, 'total' => $total]);
                    call_user_func($setProgressHandler, $count, $total, $progressLabel);
                }

                $filePath = FileHelper::normalizePath($repositoryPath.'/'.$siteUri->uri.'/index.html');

                $value = Blitz::$plugin->cacheStorage->get($siteUri);

                if (empty($value)) {
                    // Delete the file if it exists
                    FileHelper::unlink($filePath);

                    continue;
                }

                $this->_save($value, $filePath);
            }

            if (is_callable($setProgressHandler)) {
                $progressLabel = Craft::t('blitz', 'Deploying to remote.');
                call_user_func($setProgressHandler, $count, $total, $progressLabel);
            }

            $branch = $this->gitRepositories[$siteUid]['branch'] ?: $this->defaultBranch;
            $remote = $this->gitRepositories[$siteUid]['remote'] ?: $this->defaultRemote;

            $this->_deploy($repositoryPath, $branch, $remote);
        }
    }

    /**
     * @inheritdoc
     */
    public function test(): bool
    {
        foreach ($this->gitRepositories as $siteUid => $gitRepository) {
            $repositoryPath = $this->_getRepositoryPath($siteUid);

            if ($repositoryPath === null) {
                continue;
            }

            $remote = $gitRepository['remote'] ?: $this->defaultRemote;

            try {
                $git = $this->_getGitWorkingCopy($repositoryPath, $remote);

                $git->fetch($remote);
            }
            catch (GitException $e) {
                $error = Craft::t('blitz', 'Error connecting to repository: {error}', [
                    'error' => $e->getMessage(),
                ]);

                $this->addError('gitRepositories', $error);
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
     * @return string
     */
    public function getPersonalAccessToken(): string
    {
        $personalAccessToken = Craft::parseEnv($this->personalAccessToken);

        if (!is_string($personalAccessToken)) {
            return '';
        }

        return $personalAccessToken;
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
     * Returns the repository path for a given site UID
     *
     * @param string $siteUid
     *
     * @return string|null
     */
    private function _getRepositoryPath(string $siteUid)
    {
        $repositoryPath = $this->gitRepositories[$siteUid]['repositoryPath'] ?? null;

        if (empty($repositoryPath)) {
            return null;
        }

        $repositoryPath = Craft::parseEnv($this->gitRepositories[$siteUid]['repositoryPath']);

        if (!is_string($repositoryPath)) {
            return null;
        }

        $repositoryPath = FileHelper::normalizePath($repositoryPath);

        if (FileHelper::isWritable($repositoryPath) === false) {
            Blitz::$plugin->log('Repository path `{path}` is not writeable.', [
                'path' => $repositoryPath
            ], 'error');

            return null;
        }

        return $repositoryPath;
    }

    /**
     * Returns a git working copy
     *
     * @param string $repositoryPath
     * @param string $remote
     *
     * @return GitWorkingCopy
     */
    private function _getGitWorkingCopy(string $repositoryPath, string $remote): GitWorkingCopy
    {
        // Find the git binary
        $process = new Process(['which', 'git']);
        $process->run();
        $gitPath = trim($process->getOutput()) ?: null;

        $gitWrapper = new GitWrapper($gitPath);

        // Get working copy
        $git = $gitWrapper->workingCopy($repositoryPath);

        // Set user in config
        $git->config('user.name', $this->name);
        $git->config('user.email', $this->email);

        // Clear output (important!)
        // TODO: remove in Blitz 4 when GitWrapper 2 is forced
        if (method_exists($git, 'clearOutput')) {
            $git->clearOutput();
        }

        $remoteUrl = $git->getRemote($remote)['push'];

        // Break the URL into parts and reconstruct with personal access token
        $remoteUrl = (parse_url($remoteUrl, PHP_URL_SCHEME) ?: 'https').'://'
            .$this->_getAuthenticationToken($remoteUrl).'@'
            .parse_url($remoteUrl, PHP_URL_HOST)
            .parse_url($remoteUrl, PHP_URL_PATH);

        $git->remote('set-url', $remote, $remoteUrl);

        return $git;
    }

    /**
     * Returns the authentication token based on the quirks of the Git server
     *
     * @param string $url
     *
     * @return string
     */
    public function _getAuthenticationToken(string $url): string
    {
        // Default `{personalAccessToken}`
        $token = $this->getPersonalAccessToken();

        // GitLab `{personalAccessToken}:{personalAccessToken}`
        if (strpos($url, 'gitlab.com') !== false) {
            $token = $token.':'.$token;
        }

        // BitBucket `{username}:{personalAccessToken}`
        if (strpos($url, 'bitbucket.org') !== false) {
            $token = $this->username.':'.$token;
        }

        return $token;
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

    /**
     * Deploys to the remote repository.
     *
     * @param string $repositoryPath
     * @param string $branch
     * @param string $remote
     */
    private function _deploy(string $repositoryPath, string $branch, string $remote)
    {
        $event = new CancelableEvent();
        $this->trigger(self::EVENT_BEFORE_COMMIT, $event);

        if (!$event->isValid) {
            return;
        }

        $this->_runCommands($this->commandsBefore);

        try {
            $git = $this->_getGitWorkingCopy($repositoryPath, $remote);

            // Pull down any remote commits
            $git->pull();

            // Add all files to branch and check it out
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
     * @param string|string[] $commands
     */
    private function _runCommands($commands)
    {
        if (empty($commands)) {
            return;
        }

        if (is_string($commands)) {
            $commands = preg_split('/\R/', $commands);
        }

        /** @var string $command */
        foreach ($commands as $command) {
            // TODO: remove in Blitz 4 when Process 4 is forced
            if (method_exists(Process::class, 'fromShellCommandline')) {
                $process = Process::fromShellCommandline($command);
            }
            else {
                $process = new Process($command);
            }

            $process->mustRun();
        }
    }
}
