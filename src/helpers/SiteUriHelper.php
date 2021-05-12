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

        return array_merge(...$siteUriSets);
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

        $paginatedUris = self::getPaginatedUrisForSite($siteId);

        // Get URIs from all elements in the site
        $elementUris = Element_SiteSettings::find()
            ->select('uri')
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
        $uris = array_unique(array_merge($cachedUris, $paginatedUris, $elementUris));

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
     * Returns paginated URIs for a given site.
     *
     * @param int $siteId
     *
     * @return string[]
     */
    public static function getPaginatedUrisForSite(int $siteId): array
    {
        $paginatedUris = [];

        $pageTrigger = Craft::$app->getConfig()->getGeneral()->getPageTrigger();

        $cacheRecords = CacheRecord::find()
            ->select(['uri', 'paginate'])
            ->where(['siteId' => $siteId])
            ->andWhere(['not', ['paginate' => null]])
            ->all();

        foreach ($cacheRecords as $cacheRecord) {
            for ($page = 2; $page <= $cacheRecord->paginate; $page++) {
                $paginatedUris[] = $cacheRecord->uri.'/'.$pageTrigger.$page;
            }
        }

        return $paginatedUris;
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

            if ($siteUri === null) {
                continue;
            }

            // Deal with wildcard in URL
            if (strpos($url, '*') !== false) {
                $wildcardUris = CacheRecord::find()
                    ->select('uri')
                    ->where(['siteId' => $siteUri->siteId])
                    ->andWhere(['like', 'uri', str_replace('*', '%', $siteUri->uri), false])
                    ->column();

                foreach ($wildcardUris as $wildcardUri) {
                    $siteUris[] = new SiteUriModel([
                        'siteId' => $siteUri->siteId,
                        'uri' => $wildcardUri,
                    ]);
                }
            }
            else {
                $siteUris[] = $siteUri;
            }
        }

        return $siteUris;
    }

    /**
     * Returns site URI from a given URL.
     *
     * This method looks for the site with the longest base URL that matches
     * the provided URL. For example, the URL `site.com/en/page` will match
     * the site with base URL `site.com/en` over `site.com`.
     *
     * @param string $url
     *
     * @return SiteUriModel|null
     */
    public static function getSiteUriFromUrl(string $url)
    {
        $siteUri = null;
        $siteBaseUrl = '';

        foreach (Craft::$app->getSites()->getAllSites() as $site) {
            $baseUrl = trim(Craft::getAlias($site->getBaseUrl()), '/');

            // If the URL begins with the base URL and the base URL is longer than any already found.
            if (stripos($url, $baseUrl) === 0 && strlen($baseUrl) > strlen($siteBaseUrl)) {
                $siteBaseUrl = $baseUrl;

                $uri = preg_replace('/'.preg_quote($baseUrl, '/').'/', '', $url, 1);
                $uri = trim($uri, '/');

                $siteUri = new SiteUriModel([
                    'siteId' => $site->id,
                    'uri' => $uri,
                ]);
            }
        }

        return $siteUri;
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
