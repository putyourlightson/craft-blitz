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
     * Carries the batch number forward so continuation jobs use Craft’s batch descriptions.
     */
    public int $batchIndex = 0;

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
        $description = $this->description ?? Craft::t('blitz', 'Generating Blitz cache');
        $count = SiteUriQueueHelper::getCount(CacheGeneratorHelper::DRIVER_ID, 'generateUrisWithProgress');
        $totalBatches = $this->batchIndex + max(1, (int)ceil($count / Blitz::$plugin->settings->driverJobBatchSize));
        if ($totalBatches <= 1) {
            return $description;
        }

        return Craft::t('app', '{description} (batch {index, number} of {total, number})', [
            'description' => $description,
            'index' => $this->batchIndex + 1,
            'total' => $totalBatches,
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
            $nextJob = new self([
                'description' => $this->description,
                'batchIndex' => $this->batchIndex + 1,
            ]);
            QueueHelper::push($nextJob, $priority - 1, 0, $this->getTtr(), $queue);
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
