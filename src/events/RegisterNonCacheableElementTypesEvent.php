<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\events;

use yii\base\Event;

class RegisterNonCacheableElementTypesEvent extends Event
{
    // Properties
    // =========================================================================

    /**
     * @var array
     */
    public $elementTypes = [];
}
