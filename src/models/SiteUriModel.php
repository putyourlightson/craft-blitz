<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\models;

use craft\base\Model;
use craft\helpers\UrlHelper;

/**
 * @property string $url
 */
class SiteUriModel extends Model
{
    /**
     * @var string|int
     */
    public string|int $siteId;

    /**
     * @var string
     */
    public string $uri;

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();

        // Normalize the URI attribute
        $this->uri = str_replace('__home__', '', $this->uri);
    }

    /**
     * Returns a URL.
     */
    public function getUrl(): string
    {
        return UrlHelper::siteUrl($this->uri, null, null, $this->siteId);
    }
}
