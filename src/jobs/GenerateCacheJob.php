<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\jobs;

use Craft;
use craft\helpers\Queue as QueueHelper;
use craft\queue\BaseJob;
use craft\queue\Queue as CraftQueue;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\helpers\CacheGeneratorHelper;
use putyourlightson\blitz\helpers\SiteUriQueueHelper;
use yii\queue\Queue;
use yii\queue\RetryableJobInterface;

/**
 * @since 4.14.0
 */
class GenerateCacheJob extends BaseJob implements RetryableJobInterface
{
    /**
     * Used to set the progress on the appropriate queue.
     *
     * @see self::setProgressHandler()
     */
    private Queue $queue;

    /**
     * @inheritdoc
     */
    public function getDescription(): string
    {
        $count = SiteUriQueueHelper::getCount(CacheGeneratorHelper::DRIVER_ID, 'generateUrisWithProgress');
        $batches = (int)ceil($count / Blitz::$plugin->settings->driverJobBatchSize);

        return Craft::t('blitz', '{description} ({count, plural, =1{# URL remaining} other{# URLs remaining}}, {batches, plural, =1{# batch remaining} other{# batches remaining}})', [
            'description' => parent::getDescription() ?? Craft::t('blitz', 'Generating Blitz cache'),
            'count' => $count,
            'batches' => $batches,
        ]);
    }

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
     * Generates the cache for a batch of site URIs in one go.
     */
    public function execute($queue): void
    {
        $this->queue = $queue;
        SiteUriQueueHelper::process(CacheGeneratorHelper::DRIVER_ID, 'generateUrisWithProgress',
            fn(array $siteUris) => Blitz::$plugin->cacheGenerator->generateUrisWithProgress($siteUris, [$this, 'setProgressHandler']),
            Blitz::$plugin->settings->driverJobBatchSize,
        );

        // Make sure the job is still reserved before spawning another job.
        if ($queue instanceof CraftQueue && !$queue->isReserved($queue->getJobId())) {
            return;
        }
        $priority = SiteUriQueueHelper::getPriority(CacheGeneratorHelper::DRIVER_ID, 'generateUrisWithProgress');
        if ($priority !== null) {
            // Continue generation before deployment jobs at the same priority.
            QueueHelper::push(new self(['description' => $this->description]), $priority - 1, 0, $this->getTtr(), $queue);
        }
    }

    /**
     * Handles setting the progress.
     */
    public function setProgressHandler(int $count, int $total, string $label = null): void
    {
        $progress = $total > 0 ? ($count / $total) : 0;

        $this->setProgress($this->queue, $progress, $label);
    }
}
