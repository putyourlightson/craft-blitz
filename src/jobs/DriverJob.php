<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\jobs;

use craft\helpers\App;
use craft\queue\BaseJob;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\models\SiteUriModel;
use yii\queue\Queue;

class DriverJob extends BaseJob
{
    // Properties
    // =========================================================================

    /**
     * @var SiteUriModel[]
     */
    public $siteUris;

    /**
     * @var string
     */
    public $driverId;

    /**
     * @var string
     */
    public $driverMethod;

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

        // Get driver from ID
        $driver = Blitz::$plugin->get($this->driverId);

        if ($driver !== null && is_callable([$driver, $this->driverMethod])) {
            call_user_func([$driver, $this->driverMethod], $this->siteUris, [$this, 'setProgressHandler']);
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
