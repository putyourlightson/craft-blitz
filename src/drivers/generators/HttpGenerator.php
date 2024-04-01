<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\drivers\generators;

use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\HttpException;
use Amp\Http\Client\Request;
use Amp\Pipeline\Pipeline;
use Craft;
use putyourlightson\blitz\Blitz;

/**
 * This generator makes concurrent HTTP requests to generate each individual
 * site URI, using a token with a generate action route to break through existing
 * cache storage and reverse proxy caches.
 *
 * The AMPHP framework is used for making HTTP requests and a concurrent
 * iterator is used to send the requests concurrently.
 * See https://amphp.org/http-client/ and https://amphp.org/pipeline
 *
 * @property-read null|string $settingsHtml
 */
class HttpGenerator extends BaseCacheGenerator
{
    /**
     * @var int The max number of concurrent requests.
     */
    public int $concurrency = 3;

    /**
     * @var int The timeout for requests in seconds.
     */
    public int $timeout = 60;

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('blitz', 'HTTP Generator');
    }

    /**
     * Generates site URIs with progress using a Concurrent Iterator.
     * See https://amphp.org/sync#approach-4-concurrentiterator
     */
    public function generateUrisWithProgress(array $siteUris, callable $setProgressHandler = null): void
    {
        $urls = $this->getUrlsToGenerate($siteUris);

        $this->generateUrlsWithProgress($urls, $setProgressHandler, 0, count($urls));
    }

    /**
     * @inheritdoc
     */
    public function getSettingsHtml(): ?string
    {
        return Craft::$app->getView()->renderTemplate('blitz/_drivers/generators/http/settings', [
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

    protected function generateUrlsWithProgress(array $urls, callable $setProgressHandler, int $count, int $total): void
    {
        $client = HttpClientBuilder::buildDefault();

        $concurrentIterator = Pipeline::fromIterable($urls)
            ->concurrent($this->concurrency);

        foreach ($concurrentIterator as $url) {
            $count++;

            try {
                $request = $this->createRequest($url);
                $response = $client->request($request);

                if ($response->getStatus() === 200) {
                    $this->generated++;
                    $this->outputVerbose($url);
                } else {
                    Blitz::$plugin->debug('{status} error: {reason}', [
                        'status' => $response->getStatus(),
                        'reason' => $response->getReason(),
                    ], $url);
                    $this->outputVerbose($url, false);
                }

                if (is_callable($setProgressHandler)) {
                    $this->callProgressHandler($setProgressHandler, $count, $total);
                }
            } catch (HttpException $exception) {
                Blitz::$plugin->debug($exception->getMessage());
                $this->outputVerbose($url, false);
            }
        }
    }

    protected function createRequest(string $url): Request
    {
        $request = new Request($url);

        // Set all timeout types, since at least two have been reported:
        // https://github.com/putyourlightson/craft-blitz/issues/467#issuecomment-1410308809
        $request->setTcpConnectTimeout($this->timeout);
        $request->setTlsHandshakeTimeout($this->timeout);
        $request->setTransferTimeout($this->timeout);
        $request->setInactivityTimeout($this->timeout);

        return $request;
    }
}
