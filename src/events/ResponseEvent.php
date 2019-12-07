<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\events;

use craft\events\CancelableEvent;
use craft\web\Response;

class ResponseEvent extends CancelableEvent
{
    // Properties
    // =========================================================================

    /**
     * @var Response
     */
    public $response;
}
