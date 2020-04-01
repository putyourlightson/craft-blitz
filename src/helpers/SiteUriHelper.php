<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\helpers;

use Craft;
use craft\helpers\FileHelper;
use craft\records\Element;
use craft\records\Element_SiteSettings;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\models\SiteUriModel;
use putyourlightson\blitz\records\CacheRecord;

class SiteUriHelper
{
    // Constants
    // =========================================================================

    /**
     * @const string
     */
    const MIME_TYPE_HTML = 'text/html';

    // Public Methods
    // =========================================================================

    /**
     * Returns the mime type of the given site URI.
     *
     * @param SiteUriModel $siteUri
     *
     * @return string
     */
    public static function getMimeType(SiteUriModel $siteUri)
    {
        return FileHelper::getMimeTypeByExtension($siteUri->uri) ?? self::MIME_TYPE_HTML;
    }

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
        $siteUriSets = [self::getSiteUrisForSite($primarySite->id, $cacheableOnly)];

        // Loop through all sites to ensure we warm all site element URLs
        $sites = $sitesService->getAllSites();

        foreach ($sites as $site) {
            // Ignore primary site as we have already added it
            if ($site->id == $primarySite->id) {
                continue;
            }

            $siteUriSets[] = self::getSiteUrisForSite($site->id, $cacheableOnly);
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
    public static function getSiteUrisForSite(int $siteId, bool $cacheableOnly = false): array
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
                'uri' => $uri,
            ]);

            if (!$cacheableOnly || Blitz::$plugin->cacheRequest->getIsCacheableSiteUri($siteUri)) {
                $siteUris[] = $siteUri;
            }
        }

        return $siteUris;
    }

    /**
     * Returns cached site URIs given an array of cache IDs.
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
            ->asArray()
            ->all();

        foreach ($siteUris as $siteUri) {
            $siteUriModels[] = new SiteUriModel($siteUri);
        }

        return $siteUriModels;
    }

    /**
     * Returns cached site URIs given an array of element IDs.
     *
     * @param int[] $elementIds
     *
     * @return SiteUriModel[]
     */
    public static function getCachedElementSiteUris(array $elementIds): array
    {
        if (empty($elementIds)) {
            return [];
        }

        $siteUriModels = [];

        // Get the site URIs of cached pages that reference the element IDs
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
     * Returns the site URIs of an array of element IDs.
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

        // Get the site URIs of the elements themselves
        $siteUris = Element_SiteSettings::find()
            ->select(['siteId', 'uri'])
            ->where(['elementId' => $elementIds])
            ->andWhere(['not', ['uri' => null]])
            ->asArray()
            ->all();

        foreach ($siteUris as $siteUri) {
            $siteUriModels[] = new SiteUriModel($siteUri);
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
    public static function getUrlsFromSiteUris(array $siteUris): array
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
    public static function getSiteUrisFromUrls(array $urls): array
    {
        $siteUris = [];

        foreach ($urls as $url) {
            $siteUri = self::getSiteUriFromUrl($url);

            if ($siteUri !== null) {
                $siteUris[] = $siteUri;
            }
        }

        return $siteUris;
    }

    /**
     * Returns site URI from a given URL.
     *
     * @param string $url
     *
     * @return SiteUriModel|null
     */
    public static function getSiteUriFromUrl(string $url)
    {
        foreach (Craft::$app->getSites()->getAllSites() as $site) {
            $baseUrl = trim(Craft::getAlias($site->getBaseUrl()), '/');

            if (stripos($url, $baseUrl) === 0) {
                $uri = str_replace($baseUrl, '', $url);
                $uri = trim($uri, '/');

                return new SiteUriModel([
                    'siteId' => $site->id,
                    'uri' => $uri,
                ]);
            }
        }

        return null;
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
