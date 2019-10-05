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
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\events\RefreshCacheEvent;
use putyourlightson\blitz\helpers\DeployerHelper;
use putyourlightson\blitz\helpers\SiteUriHelper;
use Symfony\Component\Process\Exception\LogicException;
use Symfony\Component\Process\Exception\RuntimeException as RuntimeExceptionAlias;
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
     * Commit and push the provided site URIs.
     *
     * @param array $siteUris
     * @param callable $setProgressHandler
     */
    public function commitPushSiteUris(array $siteUris, callable $setProgressHandler)
    {
        $event = new RefreshCacheEvent(['siteUris' => $siteUris]);
        $this->trigger(self::EVENT_BEFORE_DEPLOY, $event);

        if (!$event->isValid) {
            return;
        }

        $count = 0;
        $total = count($event->siteUris);
        $label = 'Deploying {count} of {total} pages.';

        $progressLabel = Craft::t('blitz', $label, ['count' => $count, 'total' => $total]);
        call_user_func($setProgressHandler, $count / $total, $progressLabel);

        $groupedSiteUris = SiteUriHelper::getSiteUrisGroupedBySite($event->siteUris);

        foreach ($groupedSiteUris as $siteId => $siteUris) {
            $count +=$this->deploySiteUris($siteId, $siteUris);

            $progressLabel = Craft::t('blitz', $label, ['count' => $count, 'total' => $total]);
            call_user_func($setProgressHandler, $count / $total, $progressLabel);
        }

        // Fire an 'afterDeploy' event
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
            [$this, 'commitPushSiteUris'],
            Craft::t('blitz', 'Deploying cached files'),
            $delay
        );
    }

    /**
     * Deploys site URIs to the site repository.
     *
     * @param int $siteId
     * @param array $siteUris
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

            $this->save($siteUri, $repositoryPath);

            $success++;
        }

        // Commit and push repository
        $cwd = realpath(CRAFT_BASE_PATH);

        $process = new Process(['git commit', '-a', '', $cwd]);
        $process->run();

        $process = new Process(['git push origin master', '', '', $cwd]);
        $process->run();

        return $success;
    }

    /**
     * Saves a value to a file path.
     *
     * @param string $filePath
     * @param string $value
     */
    public function save(string $value, string $filePath)
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
