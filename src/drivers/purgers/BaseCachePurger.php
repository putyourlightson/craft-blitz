<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\drivers\purgers;

use Craft;
use craft\base\SavableComponent;
use putyourlightson\blitz\helpers\SiteUriHelper;
use putyourlightson\blitz\models\SiteUriModel;

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
    }

    /**
     * @inheritdoc
     */
    public function purgeSite(int $siteId)
    {
        $this->purgeUris(SiteUriHelper::getSiteSiteUris($siteId));
    }

    /**
     * @inheritdoc
     */
    public function purgeAll()
    {
        $sites = Craft::$app->getSites()->getAllSites();

        foreach ($sites as $site) {
            $this->purgeSite($site->id);
        }
    }

    /**
     * @inheritdoc
     */
    public function test(): bool
    {
        return true;
    }
}
