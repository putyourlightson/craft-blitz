<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\helpers;

use Craft;

class QueryStringHelper
{
    /**
     * Returns the valid query string params of the URI.
     */
    public static function getValidQueryStringParams(string $uri): array
    {
        $queryString = parse_url($uri, PHP_URL_QUERY) ?: '';
        parse_str($queryString, $queryParams);

        return static::getValidQueryParams($queryParams);
    }

    /**
     * Returns only valid query params.
     */
    public static function getValidQueryParams(array $queryParams): array
    {
        $generalConfig = Craft::$app->getConfig()->getGeneral();
        $invalidParams = [$generalConfig->pathParam, $generalConfig->tokenParam];
        foreach ($invalidParams as $invalidParam) {
            if (isset($queryParams[$invalidParam])) {
                unset($queryParams[$invalidParam]);
            }
        }

        return $queryParams;
    }
}
