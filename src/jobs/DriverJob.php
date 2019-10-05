<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\jobs;

use craft\helpers\App;
use craft\queue\BaseJob;
use craft\queue\Queue;

class DriverJob extends BaseJob
{
    // Properties
    // =========================================================================

    /**
     * @var array
     */
    public $siteUris = [];

    /**
     * @var callable
     */
    public $callable;

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

        if (is_callable($this->callable)) {
            call_user_func($this->callable, $this->siteUris, [$this, 'setRequestProgress']);
        }
    }

    /**
     * Sets the progress for the requests.
     *
     * @param int $count
     * @param int $total
     * @param string|null $label
     */
    public function setRequestProgress(int $count, int $total, string $label = null)
    {
        $progress = $total > 0 ? ($count / $total) : 0;

        $this->setProgress($this->_queue, $progress, $label);
    }
}
