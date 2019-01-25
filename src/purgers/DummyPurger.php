<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\purgers;

use Craft;

class DummyPurger extends BasePurger
{
    // Static
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('blitz', 'None');
    }

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function purgeUris(array $siteUris) { }

    /**
     * @inheritdoc
     */
    public function purgeAll() { }

    /**
     * @inheritdoc
     */
    public function test(): bool
    {
        return true;
    }
}