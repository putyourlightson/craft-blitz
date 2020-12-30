<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\drivers\warmers;

use Craft;
use Generator;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\helpers\CacheWarmerHelper;
use putyourlightson\blitz\helpers\SiteUriHelper;

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
        return Craft::t('blitz', 'Guzzle Warmer (recommended)');
    }

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function warmUris(array $siteUris, callable $setProgressHandler = null, int $delay = null)
    {
        $siteUris = $this->beforeWarmCache($siteUris);

        if (Craft::$app->getRequest()->getIsConsoleRequest()) {
            $this->warmUrisWithProgress($siteUris, $setProgressHandler);
        }
        else {
            CacheWarmerHelper::addWarmerJob($siteUris, 'warmUrisWithProgress', $delay);
        }

        $this->afterWarmCache($siteUris);
    }

    /**
     * Warms site URIs with progress.
     *
     * @param array $siteUris
     * @param callable|null $setProgressHandler
     * @param int|null $delay
     */
    public function warmUrisWithProgress(array $siteUris, callable $setProgressHandler = null, int $delay = null)
    {
        $urls = SiteUriHelper::getUrlsFromSiteUris($siteUris);

        $count = 0;
        $total = count($urls);
        $label = 'Warming {count} of {total} pages.';

        $this->delay($setProgressHandler, $delay, $count, $total);

        $client = Craft::createGuzzleClient();

        // Create a pool of requests for sending multiple concurrent requests
        $pool = new Pool($client, $this->_getRequests($urls), [
            'concurrency' => $this->concurrency,
            'fulfilled' => function() use (&$count, $total, $label, $setProgressHandler) {
                $count++;
                $this->warmed++;

                if (is_callable($setProgressHandler)) {
                    $progressLabel = Craft::t('blitz', $label, ['count' => $count, 'total' => $total]);
                    call_user_func($setProgressHandler, $count, $total, $progressLabel);
                }
            },
            'rejected' => function(GuzzleException $reason) use (&$count, $total, $label, $setProgressHandler) {
                $count++;

                if (is_callable($setProgressHandler)) {
                    $progressLabel = Craft::t('blitz', $label, ['count' => $count, 'total' => $total]);
                    call_user_func($setProgressHandler, $count, $total, $progressLabel);
                }

                Blitz::$plugin->debug($reason->getMessage());
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
            [['concurrency'], 'integer', 'min' => 1, 'max' => 100],
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

    // Private Methods
    // =========================================================================

    /**
     * Returns a generator to return the URL requests in a memory efficient manner
     * https://medium.com/tech-tajawal/use-memory-gently-with-yield-in-php-7e62e2480b8d
     *
     * @param array $urls
     *
     * @return Generator
     */
    private function _getRequests(array $urls): Generator
    {
        foreach ($urls as $url) {
            // Ensure URL is an absolute URL starting with http
            if (stripos($url, 'http') === 0) {
                yield new Request('GET', $url, [
                    self::WARMER_HEADER_NAME => get_class($this),
                ]);
            }
        }
    }
}
