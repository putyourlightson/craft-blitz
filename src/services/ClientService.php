<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\services;

use Craft;
use craft\base\Component;
use craft\queue\QueueInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use putyourlightson\blitz\models\SettingsModel;
use yii\log\Logger;

class ClientService extends Component
{
    // Properties
    // =========================================================================

    /**
     * @var SettingsModel
     */
    public $settings;

    // Public Methods
    // =========================================================================

    /**
     * Requests the provided URLs concurrently.
     *
     * @param array $urls
     * @param array $setProgressHandler
     * @param QueueInterface|null $queue
     *
     * @return int
     */
    public function requestUrls(array $urls, array $setProgressHandler, $queue = null): int
    {
        $success = 0;

        $client = Craft::createGuzzleClient();
        $requests = [];

        foreach ($urls as $url) {
            // Ensure URL is an absolute URL starting with http
            if (strpos($url, 'http') === 0) {
                $requests[] = new Request('GET', $url);
            }
        }

        $count = 0;
        $total = count($urls);

        // Create a pool of requests for sending multiple concurrent requests
        $pool = new Pool($client, $requests, [
            'concurrency' => $this->settings->concurrency,
            'fulfilled' => function () use (&$success, &$count, $total, $setProgressHandler, $queue) {
                $success++;
                $count++;
                call_user_func_array($setProgressHandler, [$count, $total, $queue]);
            },
            'rejected' => function ($reason) use (&$count, $total, $setProgressHandler, $queue) {
                $count++;
                call_user_func_array($setProgressHandler, [$count, $total, $queue]);

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

        return $success;
    }
}
