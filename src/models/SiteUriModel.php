<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\models;

use craft\base\Element;
use craft\base\Model;
use craft\helpers\UrlHelper;

/**
 * @property-read string $url
 */
class SiteUriModel extends Model
{
    /**
     * @var string|int|null
     */
    public string|int|null $siteId = null;

    /**
     * @var string
     */
    public string $uri = '';

    /**
     * Returns a URL with optional params.
     */
    public function getUrl(array $params = []): string
    {
        if ($this->uri === Element::HOMEPAGE_URI) {
            return UrlHelper::siteUrl('', $params, null, $this->siteId);
        }

        return UrlHelper::siteUrl($this->uri, $params, null, $this->siteId);
    }
}
