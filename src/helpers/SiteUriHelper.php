<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\helpers;

use Craft;
use craft\base\Element;
use putyourlightson\blitz\models\SiteUriModel;
use putyourlightson\blitz\records\CacheRecord;

class SiteUriHelper
{
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
        $siteUriSets = [self::getSiteSiteUris($primarySite->id)];

        // Loop through all sites to ensure we warm all site element URLs
        $sites = $sitesService->getAllSites();

        foreach ($sites as $site) {
            // Ignore primary site as we have already added it
            if ($site->id == $primarySite->id) {
                continue;
            }

            $siteUriSets[] = self::getSiteSiteUris($site->id);
        }

        $siteUris = array_merge(...$siteUriSets);

        return $siteUris;
    }

    /**
     * Returns site URIs for a given site.
     *
     * @param int $siteId
     *
     * @return SiteUriModel[]
     */
    public static function getSiteSiteUris(int $siteId): array
    {
        $siteUris = [];

        $uris = CacheRecord::find()
            ->select('uri')
            ->where(['siteId' => $siteId])
            ->column();

        // Get URIs from all element types
        $elementTypes = Craft::$app->getElements()->getAllElementTypes();

        /** @var Element $elementType */
        foreach ($elementTypes as $elementType) {
            if ($elementType::hasUris()) {
                $elements = $elementType::find()
                    ->siteId($siteId)
                    ->all();

                /** @var Element $element */
                foreach ($elements as $element) {
                    $uri = trim($element->uri, '/');
                    $uri = ($uri == '__home__' ? '' : $uri);

                    if (!in_array($uri, $uris, true)) {
                        $uris[] = $uri;
                    }
                }
            }
        }

        foreach ($uris as $uri) {
            $siteUri = new SiteUriModel([
                'siteId' => $siteId,
                'uri' => $uri,
            ]);

            if ($siteUri->getIsCacheableUri()) {
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
     * Returns URLs of given site URIs.
     *
     * @param SiteUriModel[] $siteUris
     *
     * @return string[]
     */
    public static function getUrls(array $siteUris): array
    {
        $urls = [];

        foreach ($siteUris as $siteUri) {
            $url = $siteUri->getUrl();

            if (!in_array($url, $urls)) {
                $urls[] = $url;
            }
        }

        return $urls;
    }
}