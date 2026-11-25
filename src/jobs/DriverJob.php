<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\jobs;

use craft\helpers\App;
use craft\helpers\Queue as QueueHelper;
use craft\queue\BaseJob;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\helpers\DriverQueueHelper;
use yii\queue\Queue;
use yii\queue\RetryableJobInterface;

class DriverJob extends BaseJob implements RetryableJobInterface
{
    /**
     * @var string
     */
    public string $driverId;

    /**
     * @var string
     */
    public string $driverMethod;

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
     * @inheritdoc
     */
    public function execute($queue): void
    {
        App::maxPowerCaptain();

        $this->queue = $queue;

        // Resolve the driver only when there is work so cancelled jobs can finish as no-ops.
        $processedCount = DriverQueueHelper::process($this->driverId, $this->driverMethod,
            fn(array $siteUris) => call_user_func([Blitz::$plugin->get($this->driverId), $this->driverMethod], $siteUris, [$this, 'setProgressHandler']),
        );
        $priority = DriverQueueHelper::getPendingPriority($this->driverId, $this->driverMethod);
        if ($priority !== null) {
            // Let prerequisite work run before retrying blocked URLs.
            $nextJob = new self([
                'driverId' => $this->driverId,
                'driverMethod' => $this->driverMethod,
                'description' => $this->description,
            ]);
            QueueHelper::push($nextJob, $priority, $processedCount === 0 ? 1 : 0, $this->getTtr(), $queue);
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
