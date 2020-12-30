<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\drivers\warmers;

/**
 * @since 3.6.13
 */
trait CacheWarmerTrait
{
    // Properties
    // =========================================================================

    /**
     * @var bool
     */
    public $isDummy = false;

    /**
     * @var int
     * @since 3.7.0
     */
    public $warmed = 0;
}
