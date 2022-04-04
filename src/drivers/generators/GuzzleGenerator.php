<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\drivers\generators;

use Craft;
use Generator;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\helpers\CacheGeneratorHelper;

/**
 * @property-read null|string $settingsHtml
 */
class GuzzleGenerator extends BaseCacheGenerator
{
    /**
     * @var int
     */
    public int $concurrency = 3;

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('blitz', 'Guzzle Generator');
    }

    /**
     * @inheritdoc
     */
    public function generateUris(array $siteUris, callable $setProgressHandler = null, bool $queue = true)
    {
        $siteUris = $this->beforeGenerateCache($siteUris);

        if ($queue) {
            CacheGeneratorHelper::addGeneratorJob($siteUris, 'generateUrisWithProgress');
        }
        else {
            $this->generateUrisWithProgress($siteUris, $setProgressHandler);
        }

        $this->afterGenerateCache($siteUris);
    }

    /**
     * Generates site URIs with progress.
     */
    public function generateUrisWithProgress(array $siteUris, callable $setProgressHandler = null)
    {
        $client = Craft::createGuzzleClient();
        $requests = $this->_getRequestsToGenerate($siteUris);

        $count = 0;
        $total = count($requests);

        // Create a pool of requests for sending multiple concurrent requests
        $pool = new Pool($client, $requests, [
            'concurrency' => $this->concurrency,
            'fulfilled' => function() use (&$count, $total, $setProgressHandler) {
                $count++;
                $this->generated++;

                if (is_callable($setProgressHandler)) {
                    $progressLabel = Craft::t('blitz', 'Generating {count} of {total} pages.', ['count' => $count, 'total' => $total]);
                    call_user_func($setProgressHandler, $count, $total, $progressLabel);
                }
            },
            'rejected' => function(GuzzleException $reason) use (&$count, $total, $setProgressHandler) {
                $count++;

                if (is_callable($setProgressHandler)) {
                    $progressLabel = Craft::t('blitz', 'Generating {count} of {total} pages.', ['count' => $count, 'total' => $total]);
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
    public function getSettingsHtml(): ?string
    {
        return Craft::$app->getView()->renderTemplate('blitz/_drivers/generators/guzzle/settings', [
            'generator' => $this,
        ]);
    }

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        return [
            [['concurrency'], 'required'],
            [['concurrency'], 'integer', 'min' => 1, 'max' => 100],
        ];
    }

    /**
     * Returns a generator object to return the URL requests in a memory efficient manner
     * https://medium.com/tech-tajawal/use-memory-gently-with-yield-in-php-7e62e2480b8d
     */
    private function _getRequestsToGenerate(array $siteUris): Generator
    {
        $urls = $this->getUrlsToGenerate($siteUris);

        foreach ($urls as $url) {
            yield new Request('GET', $url);
        }
    }
}
