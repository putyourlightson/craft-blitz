<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\drivers\purgers;

use Craft;
use putyourlightson\blitz\models\SiteUriModel;

class DummyCachePurger extends BaseCachePurger
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

    // Properties
    // =========================================================================

    /**
     * @var bool
     */
    public $isDummy = true;
}
