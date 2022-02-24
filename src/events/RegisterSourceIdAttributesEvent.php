<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\events;

use yii\base\Event;

class RegisterSourceIdAttributesEvent extends Event
{
    /**
     * @var string[]
     */
    public array $sourceIdAttributes = [];
}
