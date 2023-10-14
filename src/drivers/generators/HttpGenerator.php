<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\drivers\generators;

use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\HttpException;
use Amp\Http\Client\Request;
use Amp\Sync\LocalSemaphore;
use Craft;
use putyourlightson\blitz\Blitz;
use Throwable;
use yii\log\Logger;

use function Amp\Iterator\fromIterable;
use function Amp\Promise\wait;

/**
 * This generator makes concurrent HTTP requests to generate each individual
 * site URI, using a token with a generate action route to break through existing
 * cache storage and reverse proxy caches.
 *
 * The Amp PHP framework is used for making HTTP requests and a concurrent
 * iterator is used to send the requests concurrently.
 * See https://amphp.org/http-client/concurrent
 * and https://amphp.org/sync/concurrent-iterator
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
     * Generates site URIs with progress.
     */
    public function generateUrisWithProgress(array $siteUris, callable $setProgressHandler = null): void
    {
        $urls = $this->getUrlsToGenerate($siteUris);
        $pages = $this->getPageCount($siteUris);
        $count = 0;

        $client = HttpClientBuilder::buildDefault();

        // Approach 4: Concurrent Iterator
        // https://amphp.org/sync/concurrent-iterator#approach-4-concurrent-iterator
        $promise = \Amp\Sync\ConcurrentIterator\each(
            fromIterable($urls),
            new LocalSemaphore($this->concurrency),
            function(string $url) use ($setProgressHandler, &$count, $pages, $client) {
                if ($this->isPageUrl($url)) {
                    $count++;
                }

                try {
                    $request = $this->_createRequest($url);
                    $response = yield $client->request($request);

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
        );

        // Exceptions are thrown only when the promise is resolved.
        try {
            wait($promise);
        } // Catch all possible exceptions to avoid interrupting progress.
        catch (Throwable $exception) {
            Blitz::$plugin->debug($this->getAllExceptionMessages($exception));
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

    private function _createRequest(string $url): Request
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
