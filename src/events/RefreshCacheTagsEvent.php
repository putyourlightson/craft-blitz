<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\events;

use craft\events\CancelableEvent;

/**
 * @since 3.11.3
 */
class RefreshCacheTagsEvent extends CancelableEvent
{
    /**
     * @var string[]
     */
    public array $tags = [];
}
