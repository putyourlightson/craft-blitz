<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\events;

use yii\base\Event;

class RegisterLiveStatusesEvent extends Event
{
    /**
     * @var string[]
     */
    public array $liveStatuses = [];
}
