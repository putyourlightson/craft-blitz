<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\jobs;

use Craft;
use craft\helpers\App;
use craft\queue\BaseJob;
use craft\queue\Queue;
use craft\queue\QueueInterface;
use putyourlightson\blitz\Blitz;

class WarmCacheJob extends BaseJob
{
    // Properties
    // =========================================================================

    /**
     * @var string[]
     */
    public $urls = [];

    /**
     * @var callable
     */
    public $requestUrls;

    /**
     * @var Queue
     */
    private $_queue;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function execute($queue)
    {
        App::maxPowerCaptain();

        $this->_queue = $queue;

        if (is_callable($this->requestUrls)) {
            call_user_func($this->requestUrls, $this->urls, [$this, 'setRequestProgress']);
        }
    }

    /**
     * Sets the progress for the requests.
     *
     * @param int $count
     * @param int $total
     * @param QueueInterface $queue
     */
    public function setRequestProgress(int $count, int $total)
    {
        $this->setProgress($this->_queue, $count / $total,
            Craft::t('blitz', 'Warming {count} of {total} pages.', [
                'count' => $count,
                'total' => $total,
            ])
        );
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
