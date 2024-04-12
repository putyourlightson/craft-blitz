<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\helpers;

use Craft;
use craft\db\Query;
use craft\db\Table;
use craft\elements\Asset;
use craft\helpers\FileHelper;
use craft\helpers\UrlHelper;
use craft\records\Element;
use craft\records\Element_SiteSettings;
use craft\web\Request;
use craft\web\twig\variables\Paginate;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\models\SiteUriModel;
use putyourlightson\blitz\records\CacheRecord;
use putyourlightson\blitz\records\CacheTagRecord;

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

        // Use sets and the splat operator rather than array_merge for performance
        // https://goo.gl/9mntEV
        $siteUriSets = [self::getCacheableSiteUrisForSite($primarySite->id)];

        // Loop through all sites to ensure we generate all site element URLs
        $sites = $sitesService->getAllSites();

        foreach ($sites as $site) {
            // Ignore primary site as we have already added it
            if ($site->id != $primarySite->id) {
                $siteUriSets[] = self::getCacheableSiteUrisForSite($site->id);
            }
        }

        return array_merge(...$siteUriSets);
    }

    /**
     * Returns all site URIs with custom site URIs.
     *
     * @return SiteUriModel[]
     *
     * @since 4.11.0
     */
    public static function getAllSiteUrisWithCustomSiteUris(): array
    {
        return array_merge(
            self::getAllSiteUris(),
            Blitz::$plugin->settings->getCustomSiteUris(),
        );
    }

    /**
     * Returns site URIs for a given site.
     *
     * @return SiteUriModel[]
     */
    public static function getSiteUrisForSite(int $siteId): array
    {
        $siteUris = [];

        $cachedUris = CacheRecord::find()
            ->select(['uri'])
            ->where(['siteId' => $siteId])
            ->orderBy(['uri' => SORT_ASC])
            ->column();

        $paginatedUris = self::getPaginatedUrisForSite($siteId);

        // Get URIs from all elements in the site
        $elementUris = Element_SiteSettings::find()
            ->select(['uri'])
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
            ->orderBy(['uri' => SORT_ASC])
            ->column();

        // Merge arrays, order by URI and keep unique values only
        $uris = array_merge($cachedUris, $paginatedUris, $elementUris);
        sort($uris);
        $uris = array_unique($uris);

        foreach ($uris as $uri) {
            $siteUris[] = new SiteUriModel([
                'siteId' => $siteId,
                'uri' => $uri,
            ]);
        }

        return $siteUris;
    }

    /**
     * Returns cacheable site URIs for a given site.
     *
     * @return SiteUriModel[]
     */
    public static function getCacheableSiteUrisForSite(int $siteId): array
    {
        $cacheableSiteUris = [];
        $siteUris = self::getSiteUrisForSite($siteId);

        foreach ($siteUris as $siteUri) {
            if (Blitz::$plugin->cacheRequest->getIsCacheableSiteUri($siteUri)
                && Blitz::$plugin->settings->shouldGeneratePageBasedOnQueryString($siteUri->uri)
            ) {
                $cacheableSiteUris[] = $siteUri;
            }
        }

        return $cacheableSiteUris;
    }

    /**
     * Returns site URIs for a given site with custom site URIs.
     *
     * @return SiteUriModel[]
     *
     * @since 4.11.0
     */
    public static function getSiteUrisForSiteWithCustomSiteUris(int $siteId): array
    {
        return array_merge(
            self::getSiteUrisForSite($siteId),
            Blitz::$plugin->settings->getCustomSiteUris($siteId),
        );
    }

    /**
     * Returns cacheable site URIs for a given site with custom site URIs.
     *
     * @return SiteUriModel[]
     *
     * @since 4.11.0
     */
    public static function getCacheableSiteUrisForSiteWithCustomSiteUris(int $siteId): array
    {
        $cacheableSiteUris = self::getCacheableSiteUrisForSite($siteId);
        $customSiteUris = Blitz::$plugin->settings->getCustomSiteUris($siteId);

        foreach ($customSiteUris as $siteUri) {
            if (Blitz::$plugin->cacheRequest->getIsCacheableSiteUri($siteUri)
                && Blitz::$plugin->settings->shouldGeneratePageBasedOnQueryString($siteUri->uri)
            ) {
                $cacheableSiteUris[] = $siteUri;
            }
        }

        return $cacheableSiteUris;
    }

    /**
     * Returns paginated URIs for a given site.
     *
     * @return string[]
     * @see Paginate::getPageUrl()
     *
     */
    public static function getPaginatedUrisForSite(int $siteId): array
    {
        $paginatedUris = [];

        /** @var CacheRecord[] $cacheRecords */
        $cacheRecords = CacheRecord::find()
            ->select(['uri', 'paginate'])
            ->where(['siteId' => $siteId])
            ->andWhere(['not', ['paginate' => null]])
            ->orderBy(['uri' => SORT_ASC])
            ->all();

        $pageTrigger = Craft::$app->getConfig()->getGeneral()->getPageTrigger();
        $useQueryParam = str_starts_with($pageTrigger, '?');

        foreach ($cacheRecords as $cacheRecord) {
            for ($page = 2; $page <= $cacheRecord->paginate; $page++) {
                $uri = $cacheRecord->uri;

                if ($useQueryParam) {
                    $param = trim($pageTrigger, '?=');
                    $uri = UrlHelper::urlWithParams($uri, [$param => $page]);
                } else {
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
     *
     * @see Request::init()
     */
    public static function isPaginatedUri(string $uri): bool
    {
        $pageTrigger = Craft::$app->getConfig()->getGeneral()->getPageTrigger();

        if (str_starts_with($pageTrigger, '?')) {
            $pageTrigger = trim($pageTrigger, '?=');

            return (bool)preg_match('/\?(.*&)?' . $pageTrigger . '=/', $uri);
        } else {
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

        $cacheIdChunks = self::getChunkedQueryParams($cacheIds);
        foreach ($cacheIdChunks as $cacheIds) {
            /** @var array $siteUris */
            $siteUris = CacheRecord::find()
                ->select(['siteId', 'uri'])
                ->where(['id' => $cacheIds])
                ->orderBy([
                    'siteId' => SORT_ASC,
                    'uri' => SORT_ASC,
                ])
                ->asArray()
                ->all();

            foreach ($siteUris as $siteUri) {
                $siteUriModels[] = new SiteUriModel($siteUri);
            }
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

        $elementIdChunks = self::getChunkedQueryParams($elementIds);
        foreach ($elementIdChunks as $elementIds) {
            // Get the site URIs of the elements themselves
            /** @var array $siteUris */
            $siteUris = Element_SiteSettings::find()
                ->select(['siteId', 'uri'])
                ->where(['elementId' => $elementIds])
                ->andWhere([
                    'not',
                    ['uri' => null],
                ])
                ->asArray()
                ->all();

            foreach ($siteUris as $siteUri) {
                $siteUriModels[] = new SiteUriModel($siteUri);
            }
        }

        return $siteUriModels;
    }

    /**
     * Returns the site URIs of an array of asset IDs, as well as existing image
     * transform URLs.
     *
     * @param int[] $elementIds
     * @return SiteUriModel[]
     * @since 4.4.0
     */
    public static function getAssetSiteUris(array $elementIds): array
    {
        if (empty($elementIds)) {
            return [];
        }

        $urls = [];

        $elementIdChunks = self::getChunkedQueryParams($elementIds);
        foreach ($elementIdChunks as $elementIds) {
            $assets = Asset::find()
                ->id($elementIds)
                ->all();

            foreach ($assets as $asset) {
                $url = $asset->getUrl();
                $urls[] = $url;

                // Get all existing image transform URLs
                if ($asset->kind === Asset::KIND_IMAGE) {
                    $indexes = (new Query())
                        ->select([
                            'filename',
                            'transformString',
                        ])
                        ->from([Table::IMAGETRANSFORMINDEX])
                        ->where([
                            'assetId' => $asset->id,
                            'fileExists' => true,
                        ])
                        ->all();

                    foreach ($indexes as $index) {
                        $urls[] = str_replace(
                            $asset->getFilename(),
                            $index['transformString'] . '/' . $asset->getFilename(),
                            $url,
                        );
                    }
                }
            }
        }

        return self::getSiteUrisFromUrls($urls);
    }

    /**
     * Returns cache IDs from the given site URIs.
     *
     * @return int[]
     * @since 4.8.0
     */
    public static function getCacheIdsFromSiteUris(array $siteUris): array
    {
        if (empty($siteUris)) {
            return [];
        }

        $cacheIdSets = [];

        $siteUriChunks = self::getChunkedQueryParams($siteUris, 2);
        foreach ($siteUriChunks as $siteUris) {
            $condition = ['or'];

            foreach ($siteUris as $siteUri) {
                $condition[] = $siteUri->toArray();
            }

            $cacheIdSets[] = CacheRecord::find()
                ->select(['id'])
                ->where($condition)
                ->column();
        }

        return array_merge(...$cacheIdSets);
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
     * Returns site URIs from the given tags.
     *
     * @param string[] $tags
     * @return SiteUriModel[]
     * @since 4.11.0
     */
    public static function getSiteUrisFromTags(array $tags): array
    {
        $siteUriModels = [];

        /** @var array $siteUris */
        $siteUris = CacheTagRecord::find()
            ->select(['siteId', 'uri'])
            ->joinWith('cache')
            ->where(['tag' => $tags])
            ->asArray()
            ->all();

        foreach ($siteUris as $siteUri) {
            $siteUriModels[] = new SiteUriModel($siteUri);
        }

        return $siteUriModels;
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
                    ->select(['uri'])
                    ->where(['siteId' => $siteUri->siteId])
                    ->andWhere(['like', 'uri', str_replace('*', '%', $siteUri->uri), false])
                    ->orderBy(['uri' => SORT_ASC])
                    ->column();

                foreach ($wildcardUris as $wildcardUri) {
                    $siteUris[] = new SiteUriModel([
                        'siteId' => $siteUri->siteId,
                        'uri' => $wildcardUri,
                    ]);
                }
            } else {
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
                    'uri' => self::encodeQueryString($uri),
                ]);
            }
        }

        return $siteUri;
    }

    /**
     * Encodes forward slashes and square brackets in a URI’s query string.
     */
    public static function encodeQueryString(string $uri): string
    {
        $uriParts = explode('?', $uri);
        $queryString = $uriParts[1] ?? '';
        $queryString = str_replace(['/', '[', ']'], ['%2F', '%5B', '%5D'], $queryString);

        return $uriParts[0] . ($queryString ? '?' . $queryString : '');
    }

    /**
     * Returns a site URI from a given request.
     */
    public static function getSiteUriFromRequest(?Request $request = null): ?SiteUriModel
    {
        $request = $request ?? Craft::$app->getRequest();
        $params = $request->getQueryParams();

        // Ensure the path param is removed from query params
        $pathParam = Craft::$app->getConfig()->getGeneral()->pathParam;
        if (isset($params[$pathParam])) {
            unset($params[$pathParam]);
        }

        $url = UrlHelper::siteUrl($request->getPathInfo(), $params);

        return self::getSiteUriFromUrl($url);
    }

    /**
     * Returns site URIs grouped by site.
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

    /**
     * Returns site URIs flattened to arrays.
     *
     * @since 4.14.0
     */
    public static function getSiteUrisFlattenedToArrays(array $siteUris): array
    {
        $flatennedSiteUris = [];

        foreach ($siteUris as $siteUri) {
            if ($siteUri instanceof SiteUriModel) {
                $flatennedSiteUris[] = $siteUri->toArray();
            }
        }

        return $flatennedSiteUris;
    }

    /**
     * Returns a chunked array of query params, to avoid exceeding the maximum parameter limit.
     * https://github.com/putyourlightson/craft-blitz/issues/639
     *
     * @since 4.14.0
     */
    private static function getChunkedQueryParams(array $items, int $paramsPerItem = 1): array
    {
        // Divide 65000 by the number of parameters per item to ensure we never go over the hard maximum parameter limit of 65535.
        $chunkLength = (int)ceil(65000 / $paramsPerItem);

        return array_chunk($items, $chunkLength);
    }
}
