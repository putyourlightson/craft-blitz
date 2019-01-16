<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\purgers;

use Craft;

class GenericPurger extends BasePurger
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
    public function purge(array $cacheIds)
    {

    }
}