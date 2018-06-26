<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\jobs;

use Craft;
use craft\queue\BaseJob;
use GuzzleHttp\Client;

class CacheJob extends BaseJob
{
    // Properties
    // =========================================================================

    /**
     * @var array
     */
    public $elementUrls = [];

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     * @throws \Exception
     * @throws \Throwable
     */
    public function execute($queue)
    {
        $totalElements = count($this->elementUrls);
        $count = 0;

        foreach ($this->elementUrls as $elementUrl) {
            $this->setProgress($queue, $count / $totalElements);
            $count++;

            $client = new Client();
            $client->get($elementUrl);
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