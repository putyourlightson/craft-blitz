<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\events;

use putyourlightson\blitz\models\SiteUriModel;
use yii\base\Event;

class RefreshCacheEvent extends Event
{
    // Properties
    // =========================================================================

    /**
     * @var SiteUriModel[]
     */
    public $siteUris = [];
}
