<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\jobs;

use craft\helpers\Queue as QueueHelper;
use craft\queue\BaseBatchedJob;
use putyourlightson\blitz\batchers\SiteUriBatcher;
use putyourlightson\blitz\Blitz;
use yii\queue\Queue;
use yii\queue\RetryableJobInterface;

/**
 * @since 4.14.0
 */
class GenerateCacheJob extends BaseBatchedJob implements RetryableJobInterface
{
    /**
     * @var array[]
     */
    public array $siteUris;

    /**
     * @var Queue
     */
    private Queue $queue;

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
     * Generates the cache for the site URIs in one go.
     */
    public function execute($queue): void
    {
        // TODO: move this into the `BaseBatchedJob::before` method in Blitz 5.
        // Decrement (increase) priority so that subsequent batches are prioritised.
        if ($this->itemOffset === 0) {
            $this->priority--;
        }

        $this->queue = $queue;

        /** @var array $siteUris */
        $siteUris = $this->data()->getSlice($this->itemOffset, $this->batchSize);
        Blitz::$plugin->cacheGenerator->generateUrisWithProgress($siteUris, [$this, 'setProgressHandler']);
        $this->itemOffset += count($siteUris);

        // Spawn another job if there are more items
        if ($this->itemOffset < $this->totalItems()) {
            $nextJob = clone $this;
            $nextJob->batchIndex++;
            QueueHelper::push($nextJob, $this->priority, 0, $this->ttr, $queue);
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

    /**
     * @inheritdoc
     */
    protected function loadData(): SiteUriBatcher
    {
        return new SiteUriBatcher($this->siteUris);
    }

    /**
     * @inheritdoc
     */
    protected function processItem(mixed $item): void
    {
    }
}
