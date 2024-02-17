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
use yii\log\Logger;

/**
 * This generator makes concurrent HTTP requests to generate each individual
 * site URI, using a token with a generate action route to break through existing
 * cache storage and reverse proxy caches.
 *
 * The Amp PHP framework is used for making HTTP requests and a concurrent
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
     * @var int The timeout for requests in milliseconds.
     */
    public int $timeout = 120000;

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
        $pages = $this->getPageCount($siteUris);
        $count = 0;

        $client = HttpClientBuilder::buildDefault();

        $concurrentIterator = Pipeline::fromIterable($urls)
            ->concurrent($this->concurrency);

        foreach ($concurrentIterator as $url) {
            if ($this->isPageUrl($url)) {
                $count++;
            }

            try {
                $request = $this->createRequest($url);
                $response = $client->request($request);

                if ($response->getStatus() === 200) {
                    $this->generated++;
                } else {
                    Blitz::$plugin->debug('{status} error: {reason}', [
                        'status' => $response->getStatus(),
                        'reason' => $response->getReason(),
                    ], $url);
                }

                if (is_callable($setProgressHandler)) {
                    $this->callProgressHandler($setProgressHandler, $count, $pages);
                }
            } catch (HttpException $exception) {
                Blitz::$plugin->log($exception->getMessage() . ' [' . $url . ']', [], Logger::LEVEL_ERROR);
            }
        }
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

    private function createRequest(string $url): Request
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
