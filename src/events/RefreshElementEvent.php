<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\events;

use craft\base\ElementInterface;
use craft\events\CancelableEvent;

class RefreshElementEvent extends CancelableEvent
{
    // Properties
    // =========================================================================

    /**
     * @var ElementInterface|null
     */
    public $element;
}
