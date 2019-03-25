<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\drivers\purgers;

use craft\base\SavableComponent;

abstract class BaseCachePurger extends SavableComponent implements CachePurgerInterface
{
    // Traits
    // =========================================================================

    use CachePurgerTrait;

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
    public function purgeUris(array $siteUris)
    {
        foreach ($siteUris as $siteUri) {
            $this->purge($siteUri);
        }
    }
}