<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\jobs;

use craft\helpers\App;
use craft\queue\BaseJob;
use craft\queue\Queue;
use putyourlightson\blitz\models\SiteUriModel;

class DriverJob extends BaseJob
{
    // Properties
    // =========================================================================

    /**
     * @var SiteUriModel[]
     */
    public $siteUris;

    /**
     * @var callable
     */
    public $jobHandler;

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

        if (is_callable($this->jobHandler)) {
            call_user_func($this->jobHandler, $this->siteUris, [$this, 'setProgressHandler']);
        }
    }

    /**
     * Handles setting the progress.
     *
     * @param int $count
     * @param int $total
     * @param string|null $label
     */
    public function setProgressHandler(int $count, int $total, string $label = null)
    {
        $progress = $total > 0 ? ($count / $total) : 0;

        $this->setProgress($this->_queue, $progress, $label);
    }
}
