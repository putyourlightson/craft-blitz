<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\events;

use craft\events\CancelableEvent;
use craft\web\Response;
use putyourlightson\blitz\models\SiteUriModel;

class ResponseEvent extends CancelableEvent
{
    // Properties
    // =========================================================================

    /**
     * @var SiteUriModel
     */
    public $siteUri;

    /**
     * @var Response
     */
    public $response;
}
