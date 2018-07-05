<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\jobs;

use Craft;
use craft\queue\BaseJob;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
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

            try {
                $client->get($url);
            }
            catch (ClientException $exception) {}
            catch (ConnectException $exception) {}
        }
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