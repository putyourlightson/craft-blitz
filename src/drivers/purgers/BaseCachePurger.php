<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\drivers\purgers;

use Craft;
use craft\base\SavableComponent;
use putyourlightson\blitz\events\RefreshCacheEvent;
use putyourlightson\blitz\helpers\SiteUriHelper;

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
        $this->purgeUris(SiteUriHelper::getSiteSiteUris($siteId));
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
}
