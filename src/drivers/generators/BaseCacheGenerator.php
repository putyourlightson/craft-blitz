<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\drivers\generators;

use Amp\MultiReasonException;
use Craft;
use craft\base\SavableComponent;
use Exception;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\events\RefreshCacheEvent;
use putyourlightson\blitz\helpers\CacheGeneratorHelper;
use putyourlightson\blitz\helpers\SiteUriHelper;
use putyourlightson\blitz\models\SiteUriModel;

/**
 * @property-read array $siteOptions
 */
abstract class BaseCacheGenerator extends SavableComponent implements CacheGeneratorInterface
{
    use CacheGeneratorTrait;

    /**
     * @event RefreshCacheEvent The event that is triggered before the cache is generated.
     *
     * You may set [[\yii\base\ModelEvent::$isValid]] to `false` to prevent the cache from being generated.
     *
     * ```php
     * use putyourlightson\blitz\drivers\generators\BaseCacheGenerator;
     * use putyourlightson\blitz\drivers\generators\HttpGenerator;
     * use putyourlightson\blitz\events\RefreshCacheEvent;
     * use yii\base\Event;
     *
     * Event::on(HttpGenerator::class, BaseCacheGenerator::EVENT_BEFORE_GENERATE_CACHE, function(RefreshCacheEvent $event) {
     *     foreach ($event->siteUris as $key => $siteUri) {
     *         if (str_contains($siteUri->uri, 'leave-me-out-of-this')) {
     *             // Removes a single site URI.
     *             unset($event->siteUris[$key]);
     *         }
     *
     *         if (str_contains($siteUri->uri, 'leave-us-all-out-of-this')) {
     *             // Prevents the cache from being generated.
     *             return false;
     *         }
     *     }
     * });
     * ```
     */
    public const EVENT_BEFORE_GENERATE_CACHE = 'beforeGenerateCache';

    /**
     * @event RefreshCacheEvent The event that is triggered after the cache is generated.
     */
    public const EVENT_AFTER_GENERATE_CACHE = 'afterGenerateCache';

    /**
     * @event RefreshCacheEvent The event that is triggered before the entire cache is generated.
     *
     * You may set [[\yii\base\ModelEvent::$isValid]] to `false` to prevent the cache from being generated.
     *
     * ```php
     * use putyourlightson\blitz\drivers\generators\BaseCacheGenerator;
     * use putyourlightson\blitz\drivers\generators\HttpGenerator;
     * use putyourlightson\blitz\events\RefreshCacheEvent;
     * use yii\base\Event;
     *
     * Event::on(HttpGenerator::class, BaseCacheGenerator::EVENT_BEFORE_GENERATE_ALL_CACHE, function(RefreshCacheEvent $e) {
     *     return false;
     * });
     * ```
     */
    public const EVENT_BEFORE_GENERATE_ALL_CACHE = 'beforeGenerateAllCache';

    /**
     * @event RefreshCacheEvent The event that is triggered after the entire cache is generated.
     */
    public const EVENT_AFTER_GENERATE_ALL_CACHE = 'afterGenerateAllCache';

    /**
     * @const string
     */
    public const GENERATE_ACTION_ROUTE = 'blitz/generator/generate';

    /**
     * @inheritdoc
     */
    public function generateUris(array $siteUris, callable $setProgressHandler = null, bool $queue = true): void
    {
        $event = new RefreshCacheEvent(['siteUris' => $siteUris]);
        $this->trigger(self::EVENT_BEFORE_GENERATE_CACHE, $event);

        if (!$event->isValid) {
            return;
        }

        $siteUris = $event->siteUris;

        if ($queue) {
            CacheGeneratorHelper::addGeneratorJob($siteUris, 'generateUrisWithProgress');
        }
        else {
            $this->generateUrisWithProgress($siteUris, $setProgressHandler);
        }

        if ($this->hasEventHandlers(self::EVENT_AFTER_GENERATE_CACHE)) {
            $this->trigger(self::EVENT_AFTER_GENERATE_CACHE, new RefreshCacheEvent([
                'siteUris' => $siteUris,
            ]));
        }
    }

    /**
     * Generates URIs with a callable progress handler and should be overridden by subclasses.
     */
    public function generateUrisWithProgress(array $siteUris, callable $setProgressHandler = null): void
    {
    }

    /**
     * @inheritdoc
     */
    public function generateSite(int $siteId, callable $setProgressHandler = null, bool $queue = true): void
    {
        // Get custom site URIs for the provided site only
        $groupedSiteUris = SiteUriHelper::getSiteUrisGroupedBySite(Blitz::$plugin->settings->getCustomSiteUris());
        $customSiteUris = $groupedSiteUris[$siteId] ?? [];

        $siteUris = array_merge(
            SiteUriHelper::getSiteUrisForSite($siteId, true),
            $customSiteUris
        );

        $this->generateUris($siteUris, $setProgressHandler, $queue);
    }

    /**
     * @inheritdoc
     */
    public function generateAll(callable $setProgressHandler = null, bool $queue = true): void
    {
        $event = new RefreshCacheEvent();
        $this->trigger(self::EVENT_BEFORE_GENERATE_ALL_CACHE, $event);

        if (!$event->isValid) {
            return;
        }

        $siteUris = array_merge(
            SiteUriHelper::getAllSiteUris(),
            Blitz::$plugin->settings->getCustomSiteUris()
        );

        $this->generateUris($siteUris, $setProgressHandler, $queue);

        if ($this->hasEventHandlers(self::EVENT_AFTER_GENERATE_ALL_CACHE)) {
            $this->trigger(self::EVENT_AFTER_GENERATE_ALL_CACHE, $event);
        }
    }

    /**
     * Calls the provided progress handles.
     */
    protected function callProgressHandler(callable $setProgressHandler, int $count, int $pages): void
    {
        $progressLabel = Craft::t('blitz', 'Generating {count} of {pages} pages.', [
            'count' => $count,
            'pages' => $pages,
        ]);

        call_user_func($setProgressHandler, $count, $pages, $progressLabel);
    }

    /**
     * Returns URLs to generate, deleting and purging any that are not cacheable.
     *
     * @param SiteUriModel[]|array[] $siteUris
     * @return array
     */
    protected function getUrlsToGenerate(array $siteUris): array
    {
        $urls = [];
        $nonCacheableSiteUris = [];
        $params = [
            'token' => Craft::$app->getTokens()->createToken(self::GENERATE_ACTION_ROUTE),
        ];

        foreach ($siteUris as $siteUri) {
            // Convert to a site URI model if it is an array
            if (is_array($siteUri)) {
                $siteUri = new SiteUriModel($siteUri);
            }

            // Only add if a cacheable site URI
            if (Blitz::$plugin->cacheRequest->getIsCacheableSiteUri($siteUri)) {
                $urls[] = $siteUri->getUrl($params);
            }
            else {
                $nonCacheableSiteUris[] = $siteUri;
            }
        }

        // Delete and purge non-cacheable site URIs
        if (!empty($nonCacheableSiteUris)) {
            Blitz::$plugin->cacheStorage->deleteUris($nonCacheableSiteUris);
            Blitz::$plugin->cachePurger->purgeUris($nonCacheableSiteUris);
        }

        return $urls;
    }

    /**
     * Returns all messages for an exception, for easier debugging.
     */
    protected function getAllExceptionMessages(Exception $exception): string
    {
        $messages = [
            $exception->getMessage(),
        ];

        while ($exception = $exception->getPrevious()) {
            if (!($exception instanceof MultiReasonException)) {
                $messages[] = $exception->getMessage();
            }
        }

        return implode('. ', $messages);
    }

    /**
     * Returns site options.
     */
    protected function getSiteOptions(): array
    {
        $siteOptions = [];

        foreach (Craft::$app->getSites()->getAllSites() as $site) {
            $siteOptions[$site->id] = $site->name;
        }

        return $siteOptions;
    }

    /**
     * Returns the number of pages (not includes) in the provided site URIs.
     *
     * @param SiteUriModel[]|array[] $siteUris
     * @return int
     */
    protected function getPageCount(array $siteUris): int
    {
        $count = 0;

        foreach ($siteUris as $siteUri) {
            $uri = is_array($siteUri) ? $siteUri['uri'] : $siteUri->uri;
            if (!str_starts_with($uri, '_includes')) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Returns whether the provided URL is a page (not an include).
     */
    protected function isPageUrl(string $url): bool
    {
        return !str_contains($url, '_includes?action=blitz');
    }
}
