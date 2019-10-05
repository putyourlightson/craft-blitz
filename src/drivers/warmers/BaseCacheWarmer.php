<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\drivers\warmers;

use Craft;
use craft\base\SavableComponent;
use putyourlightson\blitz\helpers\SiteUriHelper;

abstract class BaseCacheWarmer extends SavableComponent implements CacheWarmerInterface
{
    // Traits
    // =========================================================================

    use CacheWarmerTrait;

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

        $this->warmSiteUris(array_merge(
            SiteUriHelper::getSiteSiteUris($siteId),
            $customSiteUris
        ));
    }

    /**
     * @inheritdoc
     */
    public function warmAll()
    {
        $this->warmSiteUris(array_merge(
            SiteUriHelper::getAllSiteUris(true),
            $this->customSiteUris
        ));
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
