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
    // Public Properties
    // =========================================================================

    /**
     * @var int
     */
    public $siteId;

    /**
     * @var string
     */
    public $uri;

    // Public Methods
    // =========================================================================

    /**
     * Returns a URL.
     */
    public function getUrl(): string
    {
        return UrlHelper::siteUrl($this->uri, null, null, $this->siteId);
    }
}
