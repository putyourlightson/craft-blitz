<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\drivers\purgers;

use Craft;

class DummyPurger extends BaseCachePurger
{
    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('blitz', 'None');
    }

    /**
     * @inerhitdoc
     */
    public bool $isDummy = true;

    /**
     * @inheritdoc
     */
    public function purgeUris(array $siteUris): void
    {
    }

    /**
     * @inheritdoc
     */
    public function purgeSite(int $siteId): void
    {
    }

    /**
     * @inheritdoc
     */
    public function purgeAll(): void
    {
    }
}
