<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\drivers\generators;

use Amp\Loop;
use Amp\Sync\LocalSemaphore;
use Craft;
use putyourlightson\blitz\helpers\CacheGeneratorHelper;

use function Amp\Iterator\fromIterable;
use function Amp\Parallel\Context\create;

/**
 * @property-read null|string $settingsHtml
 */
class LocalGenerator extends BaseCacheGenerator
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
        Loop::run(function() use ($siteUris, $setProgressHandler) {
            $urls = $this->getUrlsToGenerate($siteUris);

            $count = 0;
            $total = count($urls);
            $config = [
                'root' => Craft::getAlias('@root'),
                'webroot' => Craft::getAlias('@webroot'),
                'pathParam' => Craft::$app->getConfig()->getGeneral()->pathParam,
            ];

            // Approach 4: Concurrent Iterator
            // https://amphp.org/sync/concurrent-iterator#approach-4-concurrent-iterator
            \Amp\Sync\ConcurrentIterator\each(
                fromIterable($urls),
                new LocalSemaphore($this->concurrency),
                function($url) use ($setProgressHandler, &$count, $total, $config) {
                    $count++;
                    $config['url'] = $url;

                    // Create a context that to send data between the parent and child processes
                    // https://amphp.org/parallel/processes#parent-process
                    $context = create(__DIR__ . '/scripts/local-generator-context.php');
                    yield $context->start();
                    yield $context->send($config);
                    $result = yield $context->receive();

                    if ($result) {
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
