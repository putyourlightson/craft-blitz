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
class GuzzleWarmer extends BaseCacheWarmer
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
        return Craft::t('blitz', 'Guzzle Warmer');
    }

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function warmUris(array $siteUris, int $delay = null, callable $setProgressHandler = null)
    {
        $event = new RefreshCacheEvent(['siteUris' => $siteUris]);
        $this->trigger(self::EVENT_BEFORE_WARM_CACHE, $event);

        if (!$event->isValid) {
            return;
        }

        if (Craft::$app->getRequest()->getIsConsoleRequest()) {
            $this->warmUrisWithProgress($siteUris, $setProgressHandler);
        }
        else {
            CacheWarmerHelper::addDriverJob(
                $siteUris,
                'cacheWarmer',
                'warmUrisWithProgress',
                Craft::t('blitz', 'Warming Blitz cache'),
                $delay
            );
        }

        if ($this->hasEventHandlers(self::EVENT_AFTER_WARM_CACHE)) {
            $this->trigger(self::EVENT_AFTER_WARM_CACHE, $event);
        }
    }

    /**
     * Warms site URIs with progress.
     *
     * @param array $siteUris
     * @param callable|null $setProgressHandler
     */
    public function warmUrisWithProgress(array $siteUris, callable $setProgressHandler = null)
    {
        $client = Craft::createGuzzleClient();
        $requests = [];

        $urls = SiteUriHelper::getSiteUriUrls($siteUris);

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
            'fulfilled' => function() use (&$count, $total, $label, $setProgressHandler) {
                $count++;

                if (is_callable($setProgressHandler)) {
                    $progressLabel = Craft::t('blitz', $label, ['count' => $count, 'total' => $total]);
                    call_user_func($setProgressHandler, $count, $total, $progressLabel);
                }
            },
            'rejected' => function($reason) use (&$count, $total, $label, $setProgressHandler) {
                $count++;

                if (is_callable($setProgressHandler)) {
                    $progressLabel = Craft::t('blitz', $label, ['count' => $count, 'total' => $total]);
                    call_user_func($setProgressHandler, $count, $total, $progressLabel);
                }

                if ($reason instanceof RequestException) {
                    /** RequestException $reason */
                    preg_match('/^(.*?)\R/', $reason->getMessage(), $matches);

                    if (!empty($matches[1])) {
                        Blitz::$plugin->log(trim($matches[1], ':'), [], 'error');
                    }
                }
            },
        ]);

        // Initiate the transfers and wait for the pool of requests to complete
        $pool->promise()->wait();
    }

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
    public function getSettingsHtml()
    {
        return Craft::$app->getView()->renderTemplate('blitz/_drivers/warmers/guzzle/settings', [
            'warmer' => $this,
        ]);
    }
}
