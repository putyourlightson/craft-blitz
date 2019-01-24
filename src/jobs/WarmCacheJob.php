<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\jobs;

use Craft;
use craft\helpers\App;
use craft\queue\BaseJob;
use craft\queue\QueueInterface;
use putyourlightson\blitz\Blitz;

class WarmCacheJob extends BaseJob
{
    // Properties
    // =========================================================================

    /**
     * @var array
     */
    public $urls = [];

    /**
     * @var int
     */
    public $concurrency = 1;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function execute($queue)
    {
        App::maxPowerCaptain();

        Blitz::$plugin->client->requestUrls($this->urls, $this->concurrency, [$this, 'setRequestsProgress'], $queue);
    }

    /**
     * Sets the progress for the requests.
     *
     * @param int $count
     * @param int $total
     * @param QueueInterface $queue
     */
    public function setRequestsProgress(int $count, int $total, QueueInterface $queue)
    {
        $this->setProgress($queue, $count / $total);
    }

    // Protected Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    protected function defaultDescription(): string
    {
        return Craft::t('blitz', 'Warming Blitz cache');
    }
}
