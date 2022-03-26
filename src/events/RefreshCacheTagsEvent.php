<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\events;

use craft\events\CancelableEvent;

/**
 * @since 3.12.0
 */
class RefreshCacheTagsEvent extends CancelableEvent
{
    /**
     * @var string[]
     */
    public array $tags = [];
}
