<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\events;

use craft\events\CancelableEvent;

class OutputEvent extends CancelableEvent
{
    // Properties
    // =========================================================================

    /**
     * @var string
     */
    public $value = '';
}
