<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\drivers\warmers;

use Craft;
use craft\base\SavableComponent;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\events\RefreshCacheEvent;
use putyourlightson\blitz\helpers\SiteUriHelper;
use putyourlightson\blitz\models\SiteUriModel;

/**
 * @property-read array $siteOptions
 */
abstract class BaseCacheWarmer extends SavableComponent implements CacheWarmerInterface
{
    use CacheWarmerTrait;

    /**
     * @event RefreshCacheEvent The event that is triggered before the cache is warmed.
     *
     * You may set [[\yii\base\ModelEvent::$isValid]] to `false` to prevent the cache from being warmed.
     *
     * ```php
     * use putyourlightson\blitz\drivers\warmers\BaseCacheWarmer;
     * use putyourlightson\blitz\drivers\warmers\GuzzleWarmer;
     * use putyourlightson\blitz\events\RefreshCacheEvent;
     * use yii\base\Event;
     *
     * Event::on(GuzzleWarmer::class, BaseCacheWarmer::EVENT_BEFORE_WARM_CACHE, function(RefreshCacheEvent $e) {
     *     foreach ($e->siteUris as $key => $siteUri) {
     *         if (strpos($siteUri->uri, 'leave-me-out-of-this') !== false) {
     *             // Removes a single site URI.
     *             unset($e->siteUris[$key]);
     *         }
     *
     *         if (strpos($siteUri->uri, 'leave-us-all-out-of-this') !== false) {
     *             // Prevents the cache from being warmed.
     *             return false;
     *         }
     *     }
     * });
     * ```
     */
    public const EVENT_BEFORE_WARM_CACHE = 'beforeWarmCache';

    /**
     * @event RefreshCacheEvent The event that is triggered after the cache is warmed.
     */
    public const EVENT_AFTER_WARM_CACHE = 'afterWarmCache';

    /**
     * @event RefreshCacheEvent The event that is triggered before the entire cache is warmed.
     *
     * You may set [[\yii\base\ModelEvent::$isValid]] to `false` to prevent the cache from being warmed.
     *
     * ```php
     * use putyourlightson\blitz\drivers\warmers\BaseCacheWarmer;
     * use putyourlightson\blitz\drivers\warmers\GuzzleWarmer;
     * use putyourlightson\blitz\events\RefreshCacheEvent;
     * use yii\base\Event;
     *
     * Event::on(GuzzleWarmer::class, BaseCacheWarmer::EVENT_BEFORE_WARM_ALL_CACHE, function(RefreshCacheEvent $e) {
     *     return false;
     * });
     * ```
     */
    public const EVENT_BEFORE_WARM_ALL_CACHE = 'beforeWarmAllCache';

    /**
     * @event RefreshCacheEvent The event that is triggered after the entire cache is warmed.
     */
    public const EVENT_AFTER_WARM_ALL_CACHE = 'afterWarmAllCache';

    /**
     * @inheritdoc
     */
    public function warmSite(int $siteId, callable $setProgressHandler = null, bool $queue = true)
    {
        // Get custom site URIs for the provided site only
        $groupedSiteUris = SiteUriHelper::getSiteUrisGroupedBySite(Blitz::$plugin->settings->customSiteUris);
        $customSiteUris = $groupedSiteUris[$siteId] ?? [];

        $siteUris = array_merge(
            SiteUriHelper::getSiteUrisForSite($siteId, true),
            $customSiteUris
        );

        $this->warmUris($siteUris, $setProgressHandler, $queue);
    }

    /**
     * @inheritdoc
     */
    public function warmAll(callable $setProgressHandler = null, bool $queue = true)
    {
        $event = new RefreshCacheEvent();
        $this->trigger(self::EVENT_BEFORE_WARM_ALL_CACHE, $event);

        if (!$event->isValid) {
            return;
        }

        $siteUris = array_merge(
            SiteUriHelper::getAllSiteUris(true),
            Blitz::$plugin->settings->customSiteUris
        );

        $this->warmUris($siteUris, $setProgressHandler, $queue);

        if ($this->hasEventHandlers(self::EVENT_AFTER_WARM_ALL_CACHE)) {
            $this->trigger(self::EVENT_AFTER_WARM_ALL_CACHE, $event);
        }
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
     * Triggers the `beforeWarmCache` event.
     *
     * @param SiteUriModel[] $siteUris
     * @return SiteUriModel[]
     */
    protected function beforeWarmCache(array $siteUris): array
    {
        $event = new RefreshCacheEvent(['siteUris' => $siteUris]);
        $this->trigger(self::EVENT_BEFORE_WARM_CACHE, $event);

        if (!$event->isValid) {
            return [];
        }

        return $event->siteUris;
    }

    /**
     * Triggers the `afterWarmCache` event.
     *
     * @param SiteUriModel[] $siteUris
     */
    protected function afterWarmCache(array $siteUris)
    {
        if ($this->hasEventHandlers(self::EVENT_AFTER_WARM_CACHE)) {
            $this->trigger(self::EVENT_AFTER_WARM_CACHE, new RefreshCacheEvent([
                'siteUris' => $siteUris,
            ]));
        }
    }
}
