<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\drivers\generators;

use Amp\Loop;
use Amp\Sync\LocalSemaphore;
use Craft;
use craft\helpers\UrlHelper;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\helpers\CacheGeneratorHelper;
use putyourlightson\blitz\models\SiteUriModel;
use putyourlightson\blitz\services\CacheRequestService;

use function Amp\Iterator\fromIterable;
use function Amp\Parallel\Context\create;

class LocalGenerator extends BaseCacheGenerator
{
    /**
     * @var int
     */
    public int $concurrency = 3;

    /**
     * @see _getToken()
     */
    private string|bool|null $_token = null;

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('blitz', 'Local Generator');
    }

    /**
     * @inheritdoc
     */
    public function generateUris(array $siteUris, callable $setProgressHandler = null, bool $queue = true)
    {
        $siteUris = $this->beforeGenerateCache($siteUris);

        if (empty($siteUris)) {
            return;
        }

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
        // Event loop for running parallel processes
        // https://amphp.org/parallel/processes
        Loop::run(function () use ($siteUris, $setProgressHandler) {
            $count = 0;
            $total = count($siteUris);
            $config = [
                'basePath' => CRAFT_BASE_PATH,
                'webroot' => Craft::getAlias('@webroot'),
                'pathParam' => Craft::$app->getConfig()->getGeneral()->pathParam,
            ];

            // Approach 4: Concurrent Iterator
            // https://amphp.org/sync/concurrent-iterator#approach-4-concurrent-iterator
            \Amp\Sync\ConcurrentIterator\each(
                fromIterable($siteUris),
                new LocalSemaphore($this->concurrency),
                function ($siteUri) use ($setProgressHandler, &$count, $total, $config) {
                    $count++;
                    $url = $this->_getUrlToGenerate($siteUri);

                    if ($url === null) {
                        return;
                    }

                    $config['url'] = $url;

                    // Create a context that to send data between the parent and child processes
                    // https://amphp.org/parallel/processes#parent-process
                    $context = create(__DIR__ . '/scripts/local-generator-context.php');
                    yield $context->start();
                    yield $context->send($config);
                    $result = yield $context->receive();

                    if ($result == 1) {
                        $this->generated++;
                    }

                    if (is_callable($setProgressHandler)) {
                        $progressLabel = Craft::t('blitz', 'Generating {count} of {total} pages.', ['count' => $count, 'total' => $total]);
                        call_user_func($setProgressHandler, $count, $total, $progressLabel);
                    }
                }
            );
        });
    }

    /**
     * Returns a URL to generate, provided the site URI is cacheable.
     */
    private function _getUrlToGenerate(SiteUriModel|array $siteUri): ?string
    {
        // Convert to a SiteUriModel if it is an array
        if (is_array($siteUri)) {
            $siteUri = new SiteUriModel($siteUri);
        }

        // Only proceed if this is a cacheable site URI
        if (!Blitz::$plugin->cacheRequest->getIsCacheableSiteUri($siteUri)) {
            return null;
        }

        return UrlHelper::url($siteUri->getUrl(), [
            'token' => $this->_getToken(),
        ]);
    }

    private function _getToken(): string|bool
    {
        if ($this->_token !== null) {
            return $this->_token;
        }

        $this->_token = Craft::$app->getTokens()->createToken(CacheRequestService::GENERATE_ROUTE);

        return $this->_token;
    }
}
