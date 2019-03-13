<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\drivers\purgers;

use Craft;
use putyourlightson\blitz\models\SiteUriModel;

class DummyPurger extends BaseCachePurger
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

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function purge(SiteUriModel $siteUri)
    {
    }

    /**
     * @inheritdoc
     */
    public function purgeUris(array $siteUris)
    {
    }

    /**
     * @inheritdoc
     */
    public function purgeAll()
    {
    }

    /**
     * @inheritdoc
     */
    public function test(): bool
    {
        return true;
    }
}