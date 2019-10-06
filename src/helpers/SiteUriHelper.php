<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\helpers;

use Craft;
use craft\records\Element;
use craft\records\Element_SiteSettings;
use putyourlightson\blitz\models\SiteUriModel;
use putyourlightson\blitz\records\CacheRecord;

class SiteUriHelper
{
    /**
     * Returns all site URIs.
     *
     * @param bool $cacheableOnly
     *
     * @return SiteUriModel[]
     */
    public static function getAllSiteUris(bool $cacheableOnly = false): array
    {
        $sitesService = Craft::$app->getSites();

        // Begin with the primary site
        $primarySite = $sitesService->getPrimarySite();

        // Use sets and the splat operator rather than array_merge for performance (https://goo.gl/9mntEV)
        $siteUriSets = [self::getSiteSiteUris($primarySite->id, $cacheableOnly)];

        // Loop through all sites to ensure we warm all site element URLs
        $sites = $sitesService->getAllSites();

        foreach ($sites as $site) {
            // Ignore primary site as we have already added it
            if ($site->id == $primarySite->id) {
                continue;
            }

            $siteUriSets[] = self::getSiteSiteUris($site->id, $cacheableOnly);
        }

        $siteUris = array_merge(...$siteUriSets);

        return $siteUris;
    }

    /**
     * Returns site URIs for a given site.
     *
     * @param int $siteId
     * @param bool $cacheableOnly
     *
     * @return SiteUriModel[]
     */
    public static function getSiteSiteUris(int $siteId, bool $cacheableOnly = false): array
    {
        $siteUris = [];

        $cachedUris = CacheRecord::find()
            ->select('uri')
            ->where(['siteId' => $siteId])
            ->column();

        // Get URIs from all elements in the site
        $elementUris = Element_SiteSettings::find()
            ->select(['uri'])
            ->where([
                'siteId' => $siteId,
                'draftId' => null,
                'revisionId' => null,
                'dateDeleted' => null,
                'archived' => false,
                Element_SiteSettings::tableName().'.enabled' => true,
                Element::tableName().'.enabled' => true,
            ])
            ->andWhere(['not', ['uri' => null]])
            ->joinWith('element')
            ->column();

        // Merge arrays and keep unique values only
        $uris = array_unique(array_merge($cachedUris, $elementUris));

        foreach ($uris as $uri) {
            $siteUri = new SiteUriModel([
                'siteId' => $siteId,
                'uri' => str_replace('__home__', '', $uri),
            ]);

            if (!$cacheableOnly || $siteUri->getIsCacheableUri()) {
                $siteUris[] = $siteUri;
            }
        }

        return $siteUris;
    }

    /**
     * Returns refreshable site URIs given an array of cache IDs.
     *
     * @param int[] $cacheIds
     *
     * @return SiteUriModel[]
     */
    public static function getCachedSiteUris(array $cacheIds): array
    {
        if (empty($cacheIds)) {
            return [];
        }

        $siteUriModels = [];

        $siteUris = CacheRecord::find()
            ->select(['siteId', 'uri'])
            ->where(['id' => $cacheIds])
            ->asArray(true)
            ->all();

        foreach ($siteUris as $siteUri) {
            $siteUriModels[] = new SiteUriModel($siteUri);
        }

        return $siteUriModels;
    }

    /**
     * Returns refreshable site URIs given an array of element IDs.
     *
     * @param int[] $elementIds
     *
     * @return SiteUriModel[]
     */
    public static function getElementSiteUris(array $elementIds): array
    {
        if (empty($elementIds)) {
            return [];
        }

        $siteUriModels = [];

        $siteUris = CacheRecord::find()
            // The `id` attribute is required to make the `elements` relation work
            ->select(['id', 'siteId', 'uri'])
            ->where(['elementId' => $elementIds])
            ->joinWith('elements')
            ->all();

        foreach ($siteUris as $siteUri) {
            $siteUriModels[] = new SiteUriModel(
                // Convert to array here to remove the `id` attribute
                $siteUri->toArray(['siteId', 'uri'])
            );
        }

        return $siteUriModels;
    }

    /**
     * Returns URLs from the given site URIs.
     *
     * @param array $siteUris
     *
     * @return string[]
     */
    public static function getSiteUriUrls(array $siteUris): array
    {
        $urls = [];

        foreach ($siteUris as $siteUri) {
            // Convert to a SiteUriModel if it is an array
            if (is_array($siteUri)) {
                $siteUri = new SiteUriModel($siteUri);
            }

            $url = $siteUri->getUrl();

            if (!in_array($url, $urls)) {
                $urls[] = $url;
            }
        }

        return $urls;
    }

    /**
     * Returns site URIs from the given URLs.
     *
     * @param string[] $urls
     *
     * @return SiteUriModel[]
     */
    public static function getUrlSiteUris(array $urls): array
    {
        $siteUris = [];

        foreach (Craft::$app->getSites()->getAllSites() as $site) {
            $baseUrl = trim(Craft::getAlias($site->getBaseUrl()), '/');

            foreach ($urls as $url) {
                if (stripos($url, $baseUrl) !== 0) {
                    continue;
                }

                $uri = str_replace($baseUrl, '', $url);
                $uri = trim($uri, '/');

                $siteUris[] = new SiteUriModel([
                    'siteId' => $site->id,
                    'uri' => $uri,
                ]);
            }
        }

        return $siteUris;
    }

    /**
     * Returns site URIs grouped by site.
     *
     * @param array $siteUris
     *
     * @return array
     */
    public static function getSiteUrisGroupedBySite(array $siteUris): array
    {
        $groupedSiteUris = [];

        foreach ($siteUris as $siteUri) {
            // Convert to a SiteUriModel if it is an array
            if (is_array($siteUri)) {
                $siteUri = new SiteUriModel($siteUri);
            }

            $groupedSiteUris[$siteUri->siteId][] = $siteUri;
        }

        return $groupedSiteUris;
    }
}
