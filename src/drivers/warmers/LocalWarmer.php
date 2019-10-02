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
use putyourlightson\blitz\helpers\SiteUriHelper;
use putyourlightson\blitz\jobs\WarmCacheJob;
use putyourlightson\blitz\models\SiteUriModel;
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

    /**
     * @var string[]
     */
    public $customUrls = [];

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
    public function warm(SiteUriModel $siteUri, int $delay = null)
    {
        $this->addWarmCacheJob([$siteUri], $delay);
    }

    /**
     * @inheritdoc
     */
    public function warmUris(array $siteUris, int $delay = null)
    {
        $this->addWarmCacheJob($siteUris, $delay);
    }

    /**
     * @inheritdoc
     */
    public function warmAll()
    {
        $urls = array_unique(array_merge(
            SiteUriHelper::getAllSiteUris(true),
            $this->customUrls
        ), SORT_REGULAR);

        $this->warmUris($urls);
    }

    /**
     * @inheritdoc
     */
    public function requestUrls(array $urls, callable $setProgressHandler): int
    {
        $success = 0;

        $client = Craft::createGuzzleClient();
        $requests = [];

        // Ensure URLs are unique
        $urls = array_unique($urls);

        foreach ($urls as $url) {
            // Ensure URL is an absolute URL starting with http
            if (stripos($url, 'http') === 0) {
                $requests[] = new Request('GET', $url);
            }
        }

        $count = 0;
        $total = count($urls);

        // Create a pool of requests for sending multiple concurrent requests
        $pool = new Pool($client, $requests, [
            'concurrency' => $this->concurrency,
            'fulfilled' => function() use (&$success, &$count, $total, $setProgressHandler) {
                $success++;
                $count++;
                call_user_func($setProgressHandler, $count, $total);
            },
            'rejected' => function($reason) use (&$count, $total, $setProgressHandler) {
                $count++;
                call_user_func($setProgressHandler, $count, $total);

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
     * Adds a warm cache job to the queue.
     *
     */
    protected function addWarmCacheJob(array $siteUris, int $delay = null)
    {
        // Add job to queue with a priority and delay if provided
        Craft::$app->getQueue()
            ->priority(Blitz::$plugin->settings->warmCacheJobPriority)
            ->delay($delay)
            ->push(new WarmCacheJob([
                'urls' => SiteUriHelper::getUrls($siteUris),
                'requestUrls' => [$this, 'requestUrls'],
            ]));
    }
}
