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
    /**
     * @var SiteUriModel
     */
    public SiteUriModel $siteUri;

    /**
     * @var Response
     */
    public Response $response;
}
