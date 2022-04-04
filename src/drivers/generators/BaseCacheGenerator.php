<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\drivers\generators;

use Craft;
use craft\base\SavableComponent;
use craft\helpers\UrlHelper;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\events\RefreshCacheEvent;
use putyourlightson\blitz\helpers\SiteUriHelper;
use putyourlightson\blitz\models\SiteUriModel;
use putyourlightson\blitz\services\CacheRequestService;

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
     * use putyourlightson\blitz\drivers\generators\GuzzleGenerator;
     * use putyourlightson\blitz\events\RefreshCacheEvent;
     * use yii\base\Event;
     *
     * Event::on(GuzzleGenerator::class, BaseCacheGenerator::EVENT_BEFORE_GENERATE_CACHE, function(RefreshCacheEvent $e) {
     *     foreach ($e->siteUris as $key => $siteUri) {
     *         if (strpos($siteUri->uri, 'leave-me-out-of-this') !== false) {
     *             // Removes a single site URI.
     *             unset($e->siteUris[$key]);
     *         }
     *
     *         if (strpos($siteUri->uri, 'leave-us-all-out-of-this') !== false) {
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
     * use putyourlightson\blitz\drivers\generators\GuzzleGenerator;
     * use putyourlightson\blitz\events\RefreshCacheEvent;
     * use yii\base\Event;
     *
     * Event::on(GuzzleGenerator::class, BaseCacheGenerator::EVENT_BEFORE_GENERATE_ALL_CACHE, function(RefreshCacheEvent $e) {
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
     * @inheritdoc
     */
    public function generateSite(int $siteId, callable $setProgressHandler = null, bool $queue = true)
    {
        // Get custom site URIs for the provided site only
        $groupedSiteUris = SiteUriHelper::getSiteUrisGroupedBySite(Blitz::$plugin->settings->customSiteUris);
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
    public function generateAll(callable $setProgressHandler = null, bool $queue = true)
    {
        $event = new RefreshCacheEvent();
        $this->trigger(self::EVENT_BEFORE_GENERATE_ALL_CACHE, $event);

        if (!$event->isValid) {
            return;
        }

        $siteUris = array_merge(
            SiteUriHelper::getAllSiteUris(),
            Blitz::$plugin->settings->customSiteUris
        );

        $this->generateUris($siteUris, $setProgressHandler, $queue);

        if ($this->hasEventHandlers(self::EVENT_AFTER_GENERATE_ALL_CACHE)) {
            $this->trigger(self::EVENT_AFTER_GENERATE_ALL_CACHE, $event);
        }
    }

    /**
     * Returns URLs to generate, deleting and purging any that are not cacheable.
     */
    protected function getUrlsToGenerate(array $siteUris): array
    {
        $urls = [];
        $nonCacheableSiteUris = [];
        $token = Craft::$app->getTokens()->createToken(CacheRequestService::GENERATE_ROUTE);

        foreach ($siteUris as $siteUri) {
            // Convert to a SiteUriModel if it is an array
            if (is_array($siteUri)) {
                $siteUri = new SiteUriModel($siteUri);
            }

            // Only proceed if this is a cacheable site URI
            if (!Blitz::$plugin->cacheRequest->getIsCacheableSiteUri($siteUri)) {
                $nonCacheableSiteUris[] = $siteUri;

                continue;
            }

            $urls[] = UrlHelper::url($siteUri->getUrl(), [
                'token' => $token,
            ]);
        }

        // Delete and purge non-cacheable site URIs
        if (!empty($nonCacheableSiteUris)) {
            Blitz::$plugin->cacheStorage->deleteUris($nonCacheableSiteUris);
            Blitz::$plugin->cachePurger->purgeUris($nonCacheableSiteUris);
        }

        return $urls;
    }

    /**
     * Gets site options.
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
     * Triggers the `beforeGenerateCache` event.
     *
     * @param SiteUriModel[] $siteUris
     * @return SiteUriModel[]
     */
    protected function beforeGenerateCache(array $siteUris): array
    {
        $event = new RefreshCacheEvent(['siteUris' => $siteUris]);
        $this->trigger(self::EVENT_BEFORE_GENERATE_CACHE, $event);

        if (!$event->isValid) {
            return [];
        }

        return $event->siteUris;
    }

    /**
     * Triggers the `afterGenerateCache` event.
     *
     * @param SiteUriModel[] $siteUris
     */
    protected function afterGenerateCache(array $siteUris)
    {
        if ($this->hasEventHandlers(self::EVENT_AFTER_GENERATE_CACHE)) {
            $this->trigger(self::EVENT_AFTER_GENERATE_CACHE, new RefreshCacheEvent([
                'siteUris' => $siteUris,
            ]));
        }
    }
}
