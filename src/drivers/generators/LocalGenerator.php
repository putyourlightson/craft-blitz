<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\drivers\generators;

use Amp\Sync\LocalSemaphore;
use Craft;
use putyourlightson\blitz\Blitz;
use Throwable;

use function Amp\Iterator\fromIterable;
use function Amp\Parallel\Context\create;
use function Amp\Promise\wait;

/**
 * This generator runs concurrent PHP child processes or threads to generate
 * each individual site URI. We do it this way because to handle a web request
 * properly, the Craft web application must be run. Since this generator may have
 * been run via a console request (or a CLI based queue runner), and therefore exist
 * in the context of a Craft console application, the only way to ensure that the
 * life cycle of a web request is fully performed is to bootstrap a web app and
 * mock a web request. The bootstrapping and mocking of the web request happen in
 * the `local-generator-script.php` file.
 *
 * The Amp PHP framework is used for running parallel processes and a concurrent
 * iterator is used to run the processes concurrently.
 * See https://amphp.org/parallel/processes
 * and https://amphp.org/sync/concurrent-iterator
 *
 * @property-read null|string $settingsHtml
 */
class LocalGenerator extends BaseCacheGenerator
{
    /**
     * @var int The max number of concurrent requests.
     */
    public int $concurrency = 3;

    /**
     * @var int The timeout for requests in milliseconds. This has no effect
     * except to prevent errors when the cache generator config setting exists.
     */
    public int $timeout = 0;

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('blitz', 'Local Generator');
    }

    /**
     * Generates site URIs with progress.
     */
    public function generateUrisWithProgress(array $siteUris, callable $setProgressHandler = null): void
    {
        $urls = $this->getUrlsToGenerate($siteUris);
        $pages = $this->getPageCount($siteUris);
        $count = 0;

        $config = [
            'root' => Craft::getAlias('@root'),
            'webroot' => Craft::getAlias('@webroot'),
            'pathParam' => Craft::$app->getConfig()->getGeneral()->pathParam,
        ];

        // Approach 4: Concurrent Iterator
        // https://amphp.org/sync/concurrent-iterator#approach-4-concurrent-iterator
        $promise = \Amp\Sync\ConcurrentIterator\each(
            fromIterable($urls),
            new LocalSemaphore($this->concurrency),
            function (string $url) use ($setProgressHandler, &$count, $pages, $config) {
                if ($this->isPageUrl($url)) {
                    $count++;
                }

                $config['url'] = $url;

                // Create a context to send data between the parent and child processes
                // https://amphp.org/parallel/processes#parent-process
                $context = create(__DIR__ . '/local-generator-script.php');
                yield $context->start();
                yield $context->send($config);
                $result = yield $context->receive();

                if ($result) {
                    $this->generated++;
                }

                if (is_callable($setProgressHandler)) {
                    $this->callProgressHandler($setProgressHandler, $count, $pages);
                }
            }
        );

        // Exceptions are thrown only when the promise is resolved.
        try {
            wait($promise);
        } // Catch all exceptions and errors to avoid interrupting progress.
        catch (Throwable $exception) {
            Blitz::$plugin->debug($this->getAllExceptionMessages($exception));
        }
    }

    /**
     * @inheritdoc
     */
    public function getSettingsHtml(): ?string
    {
        return Craft::$app->getView()->renderTemplate('blitz/_drivers/generators/local/settings', [
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
}
