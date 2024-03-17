<?php

namespace putyourlightson\blitz\batchers;

use craft\base\Batchable;

/**
 * @since 4.14.0
 */
class SiteUriBatcher implements Batchable
{
    public function __construct(
        private array $siteUris,
    ) {
    }

    /**
     * @inheritdoc
     */
    public function count(): int
    {
        return count($this->siteUris);
    }

    /**
     * @inheritdoc
     */
    public function getSlice(int $offset, int $limit): iterable
    {
        return array_slice($this->siteUris, $offset, $limit);
    }
}
