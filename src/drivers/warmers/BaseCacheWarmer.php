<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\drivers\warmers;

use Craft;
use craft\base\SavableComponent;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\helpers\SiteUriHelper;

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

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function warmSite(int $siteId, int $delay = null, callable $setProgressHandler = null)
    {
        // Get custom site URIs for the provided site only
        $groupedSiteUris = SiteUriHelper::getSiteUrisGroupedBySite(Blitz::$plugin->settings->customSiteUris);
        $customSiteUris = $groupedSiteUris[$siteId] ?? [];

        $siteUris = array_merge(
            SiteUriHelper::getSiteSiteUris($siteId, true),
            $customSiteUris
        );

        $this->warmUris($siteUris, $delay, $setProgressHandler);
    }

    /**
     * @inheritdoc
     */
    public function warmAll(int $delay = null, callable $setProgressHandler = null)
    {
        $siteUris = array_merge(
            SiteUriHelper::getAllSiteUris(true),
            Blitz::$plugin->settings->customSiteUris
        );

        $this->warmUris($siteUris, $delay, $setProgressHandler);
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
}
