<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\drivers\purgers;

trait PurgerTrait
{
    // Properties
    // =========================================================================

    /**
     * @var bool
     */
    public $isDummy = false;

    /**
     * @var string
     */
    public $tagHeaderName = 'Cache-Tag';

    /**
     * @var string
     */
    public $tagHeaderDelimiter = ',';

    /**
     * @var int
     */
    public $warmCacheDelay = 0;
}
