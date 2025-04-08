<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\events;

use craft\base\Event;
use putyourlightson\blitz\models\SiteUriModel;

class SiteUriEvent extends Event
{
    /**
     * @var SiteUriModel
     */
    public SiteUriModel $siteUri;
}
