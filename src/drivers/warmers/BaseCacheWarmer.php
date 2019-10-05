<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\drivers\warmers;

use Craft;
use craft\base\SavableComponent;
use putyourlightson\blitz\helpers\SiteUriHelper;

/**
 * @property array $siteOptions
 */
abstract class BaseCacheWarmer extends SavableComponent implements CacheWarmerInterface
{
    // Traits
    // =========================================================================

    use CacheWarmerTrait;

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

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function warmSite(int $siteId)
    {
        // Get custom site URIs for the provided site only
        $groupedSiteUris = SiteUriHelper::getSiteUrisGroupedBySite($this->customSiteUris);
        $customSiteUris = $groupedSiteUris[$siteId] ?? [];

        $this->warmUris(array_merge(
            SiteUriHelper::getSiteSiteUris($siteId),
            $customSiteUris
        ));
    }

    /**
     * @inheritdoc
     */
    public function warmAll()
    {
        $sites = Craft::$app->getSites()->getAllSites();

        foreach ($sites as $site) {
            $this->warmSite($site->id);
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
}
