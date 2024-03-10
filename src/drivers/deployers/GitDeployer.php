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
use Exception;
use GitElephant\Repository;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\helpers\SiteUriHelper;
use Symfony\Component\Process\Process;
use yii\base\ErrorException;
use yii\base\Event;
use yii\base\InvalidArgumentException;
use yii\log\Logger;
use yii\queue\InvalidJobException;

/**
 * @property-read null|string $settingsHtml
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
    public string $defaultBranch = 'main';

    /**
     * @var string The default remote to deploy.
     */
    public string $defaultRemote = 'origin';

    /**
     * @inheritdoc
     */
    public function deployUrisWithProgress(array $siteUris, callable $setProgressHandler = null): void
    {
        $count = 0;
        $total = 0;
        $label = 'Deploying {count} of {total} files';

        $deployGroupedSiteUris = [];
        $groupedSiteUris = SiteUriHelper::getSiteUrisGroupedBySite($siteUris);

        foreach ($groupedSiteUris as $siteId => $siteUriGroup) {
            if ($this->hasRepository($siteId)) {
                $deployGroupedSiteUris[$siteId] = $siteUriGroup;
                $total += count($siteUriGroup);
            }
        }

        if (is_callable($setProgressHandler)) {
            $progressLabel = Craft::t('blitz', $label, ['count' => $count, 'total' => $total]);
            call_user_func($setProgressHandler, $count, $total, $progressLabel);
        }

        foreach ($deployGroupedSiteUris as $siteId => $siteUriGroup) {
            $repository = $this->getRepository($siteId);

            if ($repository === null) {
                continue;
            }

            foreach ($siteUriGroup as $siteUri) {
                $count++;

                if (is_callable($setProgressHandler)) {
                    $progressLabel = Craft::t('blitz', $label, ['count' => $count, 'total' => $total]);
                    call_user_func($setProgressHandler, $count, $total, $progressLabel);
                }

                $value = Blitz::$plugin->cacheStorage->get($siteUri);
                $filePath = FileHelper::normalizePath($repository['repositoryPath'] . '/' . $siteUri->uri);

                // If the site URI has an HTML mime type, append `index.html`.
                // https://github.com/putyourlightson/craft-blitz/issues/443
                if (SiteUriHelper::hasHtmlMimeType($siteUri)) {
                    $filePath .= '/index.html';
                }

                $this->updateFile($value, $filePath);
            }

            if (is_callable($setProgressHandler)) {
                $progressLabel = Craft::t('blitz', 'Deploying to remote');
                call_user_func($setProgressHandler, $count, $total, $progressLabel);
            }

            $this->deploy($siteId);
        }
    }

    /**
     * @inheritdoc
     */
    public function test(): bool
    {
        foreach ($this->gitRepositories as $siteUid => $gitRepository) {
            $repository = $this->getRepositoryBySiteUid($siteUid);

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
                $gitRepo = $this->getGitRepository($repository['repositoryPath'], $repository['remote']);
                $gitRepo->fetch();
            } catch (Exception $exception) {
                $this->addError('gitRepositories',
                    Craft::t('blitz',
                        'Error connecting to repository: {error}',
                        ['error' => $exception->getMessage()]
                    )
                );
            }
        }

        return !$this->hasErrors();
    }

    /**
     * @inheritDoc
     */
    public function addError($attribute, $error = ''): void
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
     * @inheritdoc
     */
    protected function defineBehaviors(): array
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
    protected function defineRules(): array
    {
        return [
            [['username', 'personalAccessToken', 'name', 'email', 'commitMessage'], 'required'],
            [['email'], 'email'],
        ];
    }

    /**
     * Returns the repository for a given site UID.
     */
    private function getRepository(int $siteId): ?array
    {
        $siteUid = Db::uidById(Table::SITES, $siteId);

        if ($siteUid === null) {
            return null;
        }

        return $this->getRepositoryBySiteUid($siteUid);
    }

    /**
     * Returns the repository path for a given site UID.
     */
    private function getRepositoryBySiteUid(string $siteUid): ?array
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
    private function hasRepository(int $siteId): bool
    {
        $repository = $this->getRepository($siteId);

        return $repository !== null;
    }

    /**
     * Returns a git repository.
     */
    private function getGitRepository(string $repositoryPath, string $remote): Repository
    {
        $gitCommand = Blitz::$plugin->settings->commands['git'] ?? null;
        $gitRepo = new Repository($repositoryPath, $gitCommand);

        // Set user in config
        $gitRepo->addGlobalConfig('user.name', $this->name);
        $gitRepo->addGlobalConfig('user.email', $this->email);

        $remote = $gitRepo->getRemote($remote);
        $remote->setFetchURL($this->_getUrlWithPersonalAccessToken($remote->getFetchURL()));
        $remote->setPushURL($this->_getUrlWithPersonalAccessToken($remote->getPushURL()));

        return $gitRepo;
    }

    /**
     * Updates a file by saving the value or deleting the file if empty.
     */
    private function updateFile(string $value, string $filePath): void
    {
        if (empty($value)) {
            if (file_exists($filePath)) {
                FileHelper::unlink($filePath);
            }

            return;
        }

        try {
            FileHelper::writeToFile($filePath, $value);
        } catch (ErrorException|InvalidArgumentException $exception) {
            Blitz::$plugin->log($exception->getMessage(), [], Logger::LEVEL_ERROR);
        }
    }

    /**
     * Deploys to the remote repository.
     */
    private function deploy(int $siteId): void
    {
        $event = new CancelableEvent();
        $this->trigger(self::EVENT_BEFORE_COMMIT, $event);

        if (!$event->isValid) {
            return;
        }

        $this->runCommands($this->commandsBefore);

        $repository = $this->getRepository($siteId);

        if ($repository === null) {
            return;
        }

        try {
            $gitRepo = $this->getGitRepository($repository['repositoryPath'], $repository['remote']);

            // Pull down any remote commits without rebasing
            $gitRepo->pull(null, null, false);

            // Add all files to branch and check it out
            $gitRepo->stage();
            $gitRepo->checkout($repository['branch']);

            // Check for changes to avoid an exception being thrown
            if ($gitRepo->getStatus()->all()->isEmpty() === false) {
                // Parse twig tags in the commit message
                $commitMessage = Craft::$app->getView()->renderString($this->commitMessage);

                $gitRepo->commit(addslashes($commitMessage));
            }

            $gitRepo->push();
        } catch (Exception $exception) {
            Blitz::$plugin->log('Remote deploy failed: {error}', [
                'error' => $exception->getMessage(),
            ], Logger::LEVEL_ERROR);

            throw new InvalidJobException($exception->getMessage());
        }

        if ($this->hasEventHandlers(self::EVENT_AFTER_COMMIT)) {
            $this->trigger(self::EVENT_AFTER_COMMIT, new Event());
        }

        $this->runCommands($this->commandsAfter);
    }

    /**
     * Runs one or more commands.
     *
     * @param string[]|string $commands
     */
    private function runCommands(array|string $commands): void
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

    private function _getUrlWithPersonalAccessToken(string $url): string
    {
        return (parse_url($url, PHP_URL_SCHEME) ?: 'https') . '://'
            . $this->username . ':' . $this->getPersonalAccessToken() . '@'
            . parse_url($url, PHP_URL_HOST)
            . parse_url($url, PHP_URL_PATH);
    }
}
