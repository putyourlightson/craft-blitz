<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\drivers\purgers;

use Craft;
use craft\base\SavableComponent;
use putyourlightson\blitz\events\RefreshCacheEvent;
use putyourlightson\blitz\helpers\SiteUriHelper;
use putyourlightson\blitz\models\SiteUriModel;

abstract class BaseCachePurger extends SavableComponent implements CachePurgerInterface
{
    // Traits
    // =========================================================================

    use CachePurgerTrait;

    // Constants
    // =========================================================================

    /**
     * @event RefreshCacheEvent
     */
    const EVENT_BEFORE_PURGE_CACHE = 'beforePurgeCache';

    /**
     * @event RefreshCacheEvent
     */
    const EVENT_AFTER_PURGE_CACHE = 'afterPurgeCache';

    /**
     * @event RefreshCacheEvent
     */
    const EVENT_BEFORE_PURGE_ALL_CACHE = 'beforePurgeAllCache';

    /**
     * @event RefreshCacheEvent
     */
    const EVENT_AFTER_PURGE_ALL_CACHE = 'afterPurgeAllCache';

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function purgeSite(int $siteId)
    {
        $this->purgeUris(SiteUriHelper::getSiteUrisForSite($siteId));
    }

    /**
     * @inheritdoc
     */
    public function purgeAll()
    {
        $event = new RefreshCacheEvent();
        $this->trigger(self::EVENT_BEFORE_PURGE_ALL_CACHE, $event);

        if (!$event->isValid) {
            return;
        }

        $sites = Craft::$app->getSites()->getAllSites();

        foreach ($sites as $site) {
            $this->purgeSite($site->id);
        }

        if ($this->hasEventHandlers(self::EVENT_AFTER_PURGE_ALL_CACHE)) {
            $this->trigger(self::EVENT_AFTER_PURGE_ALL_CACHE, $event);
        }
    }

    /**
     * @inheritdoc
     */
    public function test(): bool
    {
        return true;
    }

    /**
     * Triggers the `beforePurgeCache` event.
     *
     * @param SiteUriModel[] $siteUris
     *
     * @return SiteUriModel[]
     */
    protected function beforePurgeCache(array $siteUris): array
    {
        $event = new RefreshCacheEvent(['siteUris' => $siteUris]);
        $this->trigger(self::EVENT_BEFORE_PURGE_CACHE, $event);

        if (!$event->isValid) {
            return [];
        }

        return $event->siteUris;
    }

    /**
     * Triggers the `afterPurgeCache` event.
     *
     * @param SiteUriModel[] $siteUris
     */
    protected function afterPurgeCache(array $siteUris)
    {
        if ($this->hasEventHandlers(self::EVENT_AFTER_PURGE_CACHE)) {
            $this->trigger(self::EVENT_AFTER_PURGE_CACHE, new RefreshCacheEvent([
                'siteUris' => $siteUris
            ]));
        }
    }
}
