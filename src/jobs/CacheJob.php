<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\jobs;

use Craft;
use craft\queue\BaseJob;
use GuzzleHttp\Client;
use putyourlightson\blitz\Blitz;

class CacheJob extends BaseJob
{
    // Properties
    // =========================================================================

    /**
     * @var array
     */
    public $urls = [];

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     * @throws \Exception
     * @throws \Throwable
     */
    public function execute($queue)
    {
        if (!Blitz::$plugin->getSettings()->cachingEnabled) {
            return;
        }

        $totalElements = count($this->urls);
        $count = 0;

        foreach ($this->urls as $url) {
            $this->setProgress($queue, $count / $totalElements);
            $count++;

            $client = new Client();
            $client->get($url);
        }
    }

    // Protected Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    protected function defaultDescription(): string
    {
        return Craft::t('blitz', 'Caching elements.');
    }
}