<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\jobs;

use Craft;
use craft\queue\BaseJob;
use putyourlightson\blitz\Blitz;
use yii\queue\RetryableJobInterface;

class PurgeSiteJob extends BaseJob implements RetryableJobInterface
{
    /**
     * @var int The site to purge.
     */
    public int $siteId;

    /**
     * @inheritdoc
     */
    public function getTtr(): int
    {
        return Blitz::$plugin->settings->queueJobTtr;
    }

    /**
     * @inheritdoc
     */
    public function canRetry($attempt, $error): bool
    {
        return $attempt < Blitz::$plugin->settings->maxRetryAttempts;
    }

    /**
     * @inheritdoc
     */
    public function execute($queue): void
    {
        Blitz::$plugin->cachePurger->purgeSite($this->siteId, queue: false);
    }

    /**
     * @inheritdoc
     */
    protected function defaultDescription(): ?string
    {
        return Craft::t('blitz', 'Purging Blitz cache');
    }
}
