<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\drivers\purgers;

use craft\base\SavableComponent;

abstract class BaseCachePurger extends SavableComponent implements CachePurgerInterface
{
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