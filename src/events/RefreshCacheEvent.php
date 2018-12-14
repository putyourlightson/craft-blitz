<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\events;

use yii\base\Event;

class RefreshCacheEvent extends Event
{
    // Properties
    // =========================================================================

    /**
     * @var int[]
     */
    public $cacheIds = [];
}
