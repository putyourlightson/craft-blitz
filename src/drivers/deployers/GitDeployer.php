<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\drivers\deployers;

use Craft;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\jobs\DriverJob;

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
        return Craft::t('blitz', 'Git Deployer');
    }

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function deploy()
    {
        Blitz::$plugin->cacheStorage->get();


    }

    /**
     * Deploy the provided URLs.
     *
     * @param string[] $urls
     * @param callable $setProgressHandler
     *
     * @return int
     */
    public function deployUrls(array $urls, callable $setProgressHandler): int
    {
        $success = 0;

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
     * Adds a job to the queue.
     *
     */
    protected function addJob(array $siteUris)
    {
        // Add job to queue with a priority and delay if provided
        Craft::$app->getQueue()
            ->priority(Blitz::$plugin->settings->warmCacheJobPriority)
            ->push(new DriverJob([
                'urls' => SiteUriHelper::getUrls($siteUris),
                'deployUrls' => [$this, 'deployUrls'],
            ]));
    }
}
