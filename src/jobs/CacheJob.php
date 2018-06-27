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
    public $elementIds = [];

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

        $elements = Craft::$app->getElements();

        $totalElements = count($this->elementIds);
        $count = 0;

        foreach ($this->elementIds as $elementId) {
            $this->setProgress($queue, $count / $totalElements);
            $count++;

            $url = $elements->getElementById($elementId)->getUrl();

            if ($url) {
                $client = new Client();
                $client->get($url);
            }
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