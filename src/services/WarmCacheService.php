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
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\helpers\SiteUriHelper;
use putyourlightson\blitz\jobs\WarmCacheJob;
use putyourlightson\blitz\models\SiteUriModel;
use yii\log\Logger;

class WarmCacheService extends Component
{
    // Public Methods
    // =========================================================================

    /**
     * Warms the cache using the provided URIs.
     *
     * @param SiteUriModel[] $siteUris
     * @param int|null $delay
     */
    public function warmUris(array $siteUris, int $delay = null)
    {
        Blitz::$plugin->cacheWarmer->warmUris($siteUris, $delay);
    }
    /**
     * Warms the cache of the provided site.
     *
     * @param int $siteId
     */
    public function warmSite(int $siteId)
    {
        Blitz::$plugin->cacheWarmer->warmUris(SiteUriHelper::getSiteSiteUris($siteId, true));
    }

    /**
     * Warms the entire cache.
     */
    public function warmAll()
    {
        Blitz::$plugin->cacheWarmer->warmAll();
    }


    /**
     * Requests the provided URLs concurrently.
     *
     * @param string[] $urls
     * @param callable $setProgressHandler
     * @param QueueInterface|null $queue
     *
     * @return int
     */
    public function requestUrls(array $urls, callable $setProgressHandler): int
    {
        return Blitz::$plugin->cacheWarmer->requestUrls($urls, $setProgressHandler);
    }
}
