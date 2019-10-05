<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\drivers\warmers;

use Craft;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\events\RefreshCacheEvent;
use putyourlightson\blitz\helpers\CacheWarmerHelper;
use putyourlightson\blitz\helpers\SiteUriHelper;
use yii\log\Logger;

/**
 * @property mixed $settingsHtml
 */
class LocalWarmer extends BaseCacheWarmer
{
    // Properties
    // =========================================================================

    /**
     * @var int
     */
    public $concurrency = 3;

    // Static
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('blitz', 'Local Warmer');
    }

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            [['concurrency'], 'required'],
            [['concurrency'], 'integer', 'min' => 1],
        ];
    }

    /**
     * @inheritdoc
     */
    public function warmUris(array $siteUris, int $delay = null)
    {
        $this->addDriverJob($event->siteUris, $delay);
    }

    /**
     * Requests the provided site URIs.
     *
     * @param array $siteUris
     * @param callable $setProgressHandler
     */
    public function requestSiteUris(array $siteUris, callable $setProgressHandler)
    {
        $event = new RefreshCacheEvent(['siteUris' => $siteUris]);
        $this->trigger(self::EVENT_BEFORE_WARM_CACHE, $event);

        if (!$event->isValid) {
            return 0;
        }

        $success = 0;

        $client = Craft::createGuzzleClient();
        $requests = [];

        $urls = SiteUriHelper::getUrls($event->siteUris);

        foreach ($urls as $url) {
            // Ensure URL is an absolute URL starting with http
            if (stripos($url, 'http') === 0) {
                $requests[] = new Request('GET', $url);
            }
        }

        $count = 0;
        $total = count($urls);
        $label = 'Warming {count} of {total} pages.';

        // Create a pool of requests for sending multiple concurrent requests
        $pool = new Pool($client, $requests, [
            'concurrency' => $this->concurrency,
            'fulfilled' => function() use (&$success, &$count, $total, $label, $setProgressHandler) {
                $success++;
                $count++;
                $progressLabel = Craft::t('blitz', $label, ['count' => $count, 'total' => $total]);
                call_user_func($setProgressHandler, $count / $total, $progressLabel);
            },
            'rejected' => function($reason) use (&$count, $total, $label, $setProgressHandler) {
                $count++;
                $progressLabel = Craft::t('blitz', $label, ['count' => $count, 'total' => $total]);
                call_user_func($setProgressHandler, $count / $total, $progressLabel);

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

        // Fire an 'afterWarmCache' event
        if ($this->hasEventHandlers(self::EVENT_AFTER_WARM_CACHE)) {
            $this->trigger(self::EVENT_AFTER_WARM_CACHE, $event);
        }

        // Deploy the site URIs
        Blitz::$plugin->deployer->deployUris($event->siteUris);
    }

    /**
     * @inheritdoc
     */
    public function getSettingsHtml()
    {
        return Craft::$app->getView()->renderTemplate('blitz/_drivers/warmers/local/settings', [
            'warmer' => $this,
        ]);
    }

    // Protected Methods
    // =========================================================================

    /**
     * Adds a driver job to the queue.
     *
     * @param array $siteUris
     * @param null $delay
     */
    protected function addDriverJob(array $siteUris, $delay = null)
    {
        // Add job to queue with a priority and delay
        CacheWarmerHelper::addDriverJob(
            $siteUris,
            [$this, 'requestSiteUris'],
            Craft::t('blitz', 'Warming Blitz cache'),
            $delay
        );
    }
}
