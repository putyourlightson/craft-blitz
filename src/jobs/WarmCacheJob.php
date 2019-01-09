<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\jobs;

use Craft;
use craft\helpers\App;
use craft\queue\BaseJob;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use putyourlightson\blitz\Blitz;
use yii\log\Logger;

class WarmCacheJob extends BaseJob
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
        $settings = Blitz::$plugin->getSettings();

        if (!$settings->cachingEnabled) {
            return;
        }

        App::maxPowerCaptain();

        $total = count($this->urls);
        $count = 0;
        $client = Craft::createGuzzleClient();
        $requests = [];

        foreach ($this->urls as $url) {
            $requests[] = new Request('GET', $url);
        }

        // Create a pool of requests for sending multiple concurrent requests
        $pool = new Pool($client, $requests, [
            'concurrency' => $settings->concurrency,
            'fulfilled' => function () use (&$queue, &$count, $total) {
                $count++;
                $this->setProgress($queue, $count / $total);
            },
            'rejected' => function ($reason) use (&$queue, &$count, $total) {
                $count++;
                $this->setProgress($queue, $count / $total);

                if ($reason instanceof RequestException) {
                    /** RequestException $reason */
                    preg_match('/^(.*?)\R/', $reason->getMessage(), $matches);

                    if (!empty($matches[1])) {
                        Craft::getLogger()->log(trim($matches[1], ':'), Logger::LEVEL_ERROR, 'blitz');
                    }
                }
            },
        ]);

        // Initiate the transfers and wait for the pool of requests to complete
        $pool->promise()->wait();

        Blitz::$plugin->cache->cleanElementQueryTable();
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
