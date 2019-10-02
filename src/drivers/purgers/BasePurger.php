<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\drivers\purgers;

use craft\base\SavableComponent;
use putyourlightson\blitz\models\SiteUriModel;

abstract class BasePurger extends SavableComponent implements PurgerInterface
{
    // Traits
    // =========================================================================

    use PurgerTrait;

    // Static Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function getTemplatesRoot(): array
    {
        return [];
    }

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
