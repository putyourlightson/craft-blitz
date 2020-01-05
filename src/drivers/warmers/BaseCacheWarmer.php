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
 * @property array $siteOptions
 */
abstract class BaseCacheWarmer extends SavableComponent implements CacheWarmerInterface
{
    // Constants
    // =========================================================================

    /**
     * @event RefreshCacheEvent
     */
    const EVENT_BEFORE_WARM_CACHE = 'beforeWarmCache';

    /**
     * @event RefreshCacheEvent
     */
    const EVENT_AFTER_WARM_CACHE = 'afterWarmCache';

    /**
     * @event RefreshCacheEvent
     */
    const EVENT_BEFORE_WARM_ALL_CACHE = 'beforeWarmAllCache';

    /**
     * @event RefreshCacheEvent
     */
    const EVENT_AFTER_WARM_ALL_CACHE = 'afterWarmAllCache';

    /**
     * @const string
     */
    const WARMER_HEADER_NAME = 'X-Blitz-Warmer';

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function warmSite(int $siteId, callable $setProgressHandler = null, int $delay = null)
    {
        // Get custom site URIs for the provided site only
        $groupedSiteUris = SiteUriHelper::getSiteUrisGroupedBySite(Blitz::$plugin->settings->customSiteUris);
        $customSiteUris = $groupedSiteUris[$siteId] ?? [];

        $siteUris = array_merge(
            SiteUriHelper::getSiteUrisForSite($siteId, true),
            $customSiteUris
        );

        $this->warmUris($siteUris, $setProgressHandler, $delay);
    }

    /**
     * @inheritdoc
     */
    public function warmAll(callable $setProgressHandler = null, int $delay = null)
    {
        $siteUris = SiteUriHelper::getAllSiteUris(true);

        if (is_array(Blitz::$plugin->settings->customSiteUris)) {
            $siteUris = array_merge($siteUris, Blitz::$plugin->settings->customSiteUris);
        }

        $this->warmUris($siteUris, $setProgressHandler, $delay);
    }

    /**
     * Delays warming by the provided delay value.
     *
     * @param callable|null $setProgressHandler
     * @param int|null $delay
     */
    public function delay(callable $setProgressHandler = null, int $delay = null)
    {
        if (!empty($delay)) {
            if (is_callable($setProgressHandler)) {
                $progressLabel = Craft::t('blitz', 'Waiting {delay} seconds before warming.', [
                    'delay' => $delay
                ]);
                call_user_func($setProgressHandler, 0, 0, $progressLabel);
            }

            sleep($delay);
        }
    }

    // Protected Methods
    // =========================================================================

    /**
     * Gets site options.
     *
     * @return array
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
     *
     * @return bool
     */
    protected function beforeWarmCache(array $siteUris): bool
    {
        $event = new RefreshCacheEvent(['siteUris' => $siteUris]);
        $this->trigger(self::EVENT_BEFORE_WARM_CACHE, $event);

        return $event->isValid;
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
                'siteUris' => $siteUris
            ]));
        }
    }
}
