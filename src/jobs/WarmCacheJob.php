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
     * @var string[]
     */
    public $urls = [];

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function execute($queue)
    {
        App::maxPowerCaptain();

        Blitz::$plugin->warmCache->requestUrls($this->urls, [$this, 'setRequestProgress'], $queue);
    }

    /**
     * Sets the progress for the requests.
     *
     * @param int $count
     * @param int $total
     * @param QueueInterface $queue
     */
    public function setRequestProgress(int $count, int $total, QueueInterface $queue)
    {
        $this->setProgress($queue, $count / $total,
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
