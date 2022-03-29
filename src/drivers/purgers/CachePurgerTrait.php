<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\drivers\purgers;

trait CachePurgerTrait
{
    /**
     * @var bool Whether this is a dummy purger.
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
}
