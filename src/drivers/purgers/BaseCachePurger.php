<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\drivers\purgers;

use craft\base\SavableComponent;
use putyourlightson\blitz\models\SiteUriModel;

abstract class BaseCachePurger extends SavableComponent implements CachePurgerInterface
{
    /**
     * @inheritdoc
     *
     * @param SiteUriModel[] $siteUris
     */
    public function purgeUris(array $siteUris)
    {
        foreach ($siteUris as $siteUri) {
            $this->purge($siteUri);
        }
    }
}