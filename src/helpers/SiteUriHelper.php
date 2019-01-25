<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\helpers;

use putyourlightson\blitz\models\SiteUriModel;

class SiteUriHelper
{
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
            $urls[] = $siteUri->getUrl();
        }

        return $urls;
    }
}