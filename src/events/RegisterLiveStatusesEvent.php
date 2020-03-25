<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\events;

use yii\base\Event;

class RegisterLiveStatusesEvent extends Event
{
    // Properties
    // =========================================================================

    /**
     * @var string[]
     */
    public $liveStatuses = [];
}
