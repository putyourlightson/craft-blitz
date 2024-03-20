<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\drivers\generators;

use Amp\Pipeline\Pipeline;
use Amp\TimeoutCancellation;
use Craft;
use putyourlightson\blitz\Blitz;
use Throwable;

use function Amp\Parallel\Context\startContext;

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
 * The AMPHP framework is used for running parallel processes and a concurrent
 * iterator is used to run the processes concurrently.
 * See https://amphp.org/parallel and https://amphp.org/pipeline
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
     * @var int The timeout for requests in seconds.
     */
    public int $timeout = 60;

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('blitz', 'Local Generator');
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

        $config = [
            'root' => Craft::getAlias('@root'),
            'webroot' => Craft::getAlias('@webroot'),
            'pathParam' => Craft::$app->getConfig()->getGeneral()->pathParam,
        ];

        $concurrentIterator = Pipeline::fromIterable($urls)
            ->concurrent($this->concurrency);

        foreach ($concurrentIterator as $url) {
            if ($this->isPageUrl($url)) {
                $count++;
            }

            $config['url'] = $url;

            // Create a context to send data between parent and child processes.
            // https://amphp.org/parallel#context-creation
            $context = startContext(__DIR__ . '/local-generator-script.php');

            // Create a timeout to apply to the context.
            // https://amphp.org/amp#cancellation
            $canceller = new TimeoutCancellation($this->timeout);
            $cancellerId = $canceller->subscribe(function() use ($url, $context) {
                $message = 'Local generator request timed out.';
                Blitz::$plugin->debug($message, [], $url);
                $this->outputVerbose($url, false);
            });

            try {
                $context->send($config);
                $result = $context->receive($canceller);
            } catch (Throwable $exception) {
                Blitz::$plugin->debug($exception->getMessage(), [], $url);
                $result = false;
            }

            $canceller->unsubscribe($cancellerId);

            if ($result) {
                $this->generated++;
                $this->outputVerbose($url);
            } else {
                $this->outputVerbose($url, false);
            }

            if (is_callable($setProgressHandler)) {
                $this->callProgressHandler($setProgressHandler, $count, $pages);
            }
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
