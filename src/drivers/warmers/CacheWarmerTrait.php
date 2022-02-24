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
    /**
     * @var bool
     */
    public bool $isDummy = false;

    /**
     * @var int
     * @since 3.7.0
     */
    public int $warmed = 0;
}
