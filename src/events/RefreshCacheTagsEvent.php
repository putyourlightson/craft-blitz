<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\events;

use craft\events\CancelableEvent;

class RefreshCacheTagsEvent extends CancelableEvent
{
    // Properties
    // =========================================================================

    /**
     * @var array|null
     */
    public $tags;
}
