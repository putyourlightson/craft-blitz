<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\events;

class RefreshSiteCacheEvent extends RefreshCacheEvent
{
    // Properties
    // =========================================================================

    /**
     * @var int|null
     */
    public $siteId;
}
