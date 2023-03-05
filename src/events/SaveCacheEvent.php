<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\events;

use craft\events\CancelableEvent;
use putyourlightson\blitz\models\SiteUriModel;

class SaveCacheEvent extends CancelableEvent
{
    /**
     * @var string
     */
    public string $output;

    /**
     * @var SiteUriModel
     */
    public SiteUriModel $siteUri;

    /**
     * @var int|null
     */
    public ?int $duration = null;

    /**
     * @var bool
     */
    public bool $allowEncoding = true;
}
