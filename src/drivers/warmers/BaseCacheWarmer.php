<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\drivers\warmers;

use craft\base\SavableComponent;
use putyourlightson\blitz\helpers\SiteUriHelper;

abstract class BaseCacheWarmer extends SavableComponent implements CacheWarmerInterface
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function warmUris(array $siteUris, int $delay = null)
    {
        foreach ($siteUris as $siteUri) {
            $this->warm($siteUri, $delay);
        }
    }

    /**
     * @inheritdoc
     */
    public function warmAll()
    {
        $this->warmUris(SiteUriHelper::getAllSiteUris(true));
    }
}
