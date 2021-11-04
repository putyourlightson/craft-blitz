<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\events;

use craft\events\CancelableEvent;
use putyourlightson\blitz\models\SiteUriModel;

class SaveCacheEvent extends CancelableEvent
{
    // Properties
    // =========================================================================

    /**
     * @var string
     */
    public $output;

    /**
     * @var SiteUriModel
     */
    public $siteUri;

    /**
     * @var int|null
     */
    public $duration;
}
