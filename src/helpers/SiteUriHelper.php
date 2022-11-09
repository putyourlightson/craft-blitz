<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\helpers;

use Craft;
use craft\helpers\FileHelper;
use craft\helpers\UrlHelper;
use craft\records\Element;
use craft\records\Element_SiteSettings;
use craft\web\Request;
use craft\web\twig\variables\Paginate;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\models\SiteUriModel;
use putyourlightson\blitz\records\CacheRecord;

class SiteUriHelper
{
    /**
     * @const string
     */
    public const MIME_TYPE_HTML = 'text/html';

    /**
     * Returns the mime type of the given site URI.
     */
    public static function getMimeType(SiteUriModel $siteUri): string
    {
        $uri = $siteUri->uri;

        // Remove any query string from the URI
        if (str_contains($uri, '?')) {
            $uri = substr($uri, 0, strpos($uri, '?'));
        }

        return FileHelper::getMimeTypeByExtension($uri) ?? self::MIME_TYPE_HTML;
    }

    /**
     * Returns the mime type of the given site URI.
     *
     * @since 3.12.0
     */
    public static function hasHtmlMimeType(SiteUriModel $siteUri): bool
    {
        return self::getMimeType($siteUri) === self::MIME_TYPE_HTML;
    }

    /**
     * Returns all site URIs.
     *
     * @return SiteUriModel[]
     */
    public static function getAllSiteUris(): array
    {
        $sitesService = Craft::$app->getSites();

        // Begin with the primary site
        $primarySite = $sitesService->getPrimarySite();

        // Use sets and the splat operator rather than array_merge for performance (https://goo.gl/9mntEV)
        $siteUriSets = [self::getSiteUrisForSite($primarySite->id, true)];

        // Loop through all sites to ensure we generate all site element URLs
        $sites = $sitesService->getAllSites();

        foreach ($sites as $site) {
            // Ignore primary site as we have already added it
            if ($site->id != $primarySite->id) {
                $siteUriSets[] = self::getSiteUrisForSite($site->id, true);
            }
        }

        return array_merge(...$siteUriSets);
    }

    /**
     * Returns site URIs for a given site.
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
                Element_SiteSettings::tableName() . '.enabled' => true,
                Element::tableName() . '.enabled' => true,
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
     * @see Paginate::getPageUrl()
     *
     * @return string[]
     */
    public static function getPaginatedUrisForSite(int $siteId): array
    {
        $paginatedUris = [];

        /** @var CacheRecord[] $cacheRecords */
        $cacheRecords = CacheRecord::find()
            ->select(['uri', 'paginate'])
            ->where(['siteId' => $siteId])
            ->andWhere(['not', ['paginate' => null]])
            ->all();

        $pageTrigger = Craft::$app->getConfig()->getGeneral()->getPageTrigger();
        $useQueryParam = str_starts_with($pageTrigger, '?');

        foreach ($cacheRecords as $cacheRecord) {
            for ($page = 2; $page <= $cacheRecord->paginate; $page++) {
                $uri = $cacheRecord->uri;

                if ($useQueryParam) {
                    $param = trim($pageTrigger, '?=');
                    $uri = UrlHelper::urlWithParams($uri, [$param => $page]);
                }
                else {
                    $uri = $uri ? trim($uri, '/') . '/' : $uri;
                    $uri = $uri . $pageTrigger . $page;
                }

                $paginatedUris[] = $uri;
            }
        }

        return $paginatedUris;
    }

    /**
     * Returns whether the provided URI is a paginated URI.
     * @see Request::init()
     */
    public static function isPaginatedUri(string $uri): bool
    {
        $pageTrigger = Craft::$app->getConfig()->getGeneral()->getPageTrigger();

        if (str_starts_with($pageTrigger, '?')) {
            $pageTrigger = trim($pageTrigger, '?=');

            return (bool)preg_match('/\?(.*&)?' . $pageTrigger . '=/', $uri);
        }
        else {
            $pageTrigger = preg_quote($pageTrigger, '/');

            return (bool)preg_match('/^(.*\/)?' . $pageTrigger . '\d+$/', $uri);
        }
    }

    /**
     * Returns cached site URIs given an array of cache IDs.
     *
     * @param int[] $cacheIds
     * @return SiteUriModel[]
     */
    public static function getCachedSiteUris(array $cacheIds): array
    {
        if (empty($cacheIds)) {
            return [];
        }

        $siteUriModels = [];

        /** @var array $siteUris */
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
     * @return SiteUriModel[]
     */
    public static function getElementSiteUris(array $elementIds): array
    {
        if (empty($elementIds)) {
            return [];
        }

        $siteUriModels = [];

        // Get the site URIs of the elements themselves
        /** @var array $siteUris */
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
            if (str_contains($url, '*')) {
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
     */
    public static function getSiteUriFromUrl(string $url): ?SiteUriModel
    {
        $siteUri = null;
        $siteBaseUrl = '';

        foreach (Craft::$app->getSites()->getAllSites() as $site) {
            $baseUrl = trim($site->getBaseUrl(), '/');

            // If the URL begins with the base URL and the base URL is longer than any already found.
            if (stripos($url, $baseUrl) === 0 && strlen($baseUrl) > strlen($siteBaseUrl)) {
                $siteBaseUrl = $baseUrl;

                $uri = preg_replace('/' . preg_quote($baseUrl, '/') . '/', '', $url, 1);
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
     */
    public static function getSiteUrisGroupedBySite(array $siteUris): array
    {
        $groupedSiteUris = [];

        foreach ($siteUris as $siteUri) {
            $groupedSiteUris[$siteUri->siteId][] = $siteUri;
        }

        return $groupedSiteUris;
    }
}
