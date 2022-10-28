<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\events;

class RefreshSiteCacheEvent extends RefreshCacheEvent
{
    /**
     * @var int|null
     */
    public ?int $siteId = null;
}
