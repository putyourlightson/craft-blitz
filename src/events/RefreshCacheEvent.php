<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\events;

use craft\events\CancelableEvent;
use putyourlightson\blitz\models\SiteUriModel;

class RefreshCacheEvent extends CancelableEvent
{
    // Properties
    // =========================================================================

    /**
     * @var SiteUriModel[]|null
     */
    public $siteUris;
}
