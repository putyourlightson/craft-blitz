<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\drivers\purgers;

trait CachePurgerTrait
{
    /**
     * @var bool
     */
    public bool $isDummy = false;

    /**
     * @var string
     */
    public string $tagHeaderName = 'Cache-Tag';

    /**
     * @var string
     */
    public string $tagHeaderDelimiter = ',';

    /**
     * @var int
     */
    public int $warmCacheDelay = 0;
}
