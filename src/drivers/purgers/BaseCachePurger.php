<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\drivers\purgers;

use Craft;
use craft\base\SavableComponent;
use putyourlightson\blitz\events\RefreshCacheEvent;
use putyourlightson\blitz\helpers\CachePurgerHelper;
use putyourlightson\blitz\helpers\SiteUriHelper;

abstract class BaseCachePurger extends SavableComponent implements CachePurgerInterface
{
    use CachePurgerTrait;

    /**
     * @event RefreshCacheEvent The event that is triggered before the cache is purged.
     */
    public const EVENT_BEFORE_PURGE_CACHE = 'beforePurgeCache';

    /**
     * @event RefreshCacheEvent The event that is triggered after the cache is purged.
     */
    public const EVENT_AFTER_PURGE_CACHE = 'afterPurgeCache';

    /**
     * @event RefreshCacheEvent The event that is triggered before all cache is purged.
     */
    public const EVENT_BEFORE_PURGE_ALL_CACHE = 'beforePurgeAllCache';

    /**
     * @event RefreshCacheEvent The event that is triggered after all cache is purged.
     */
    public const EVENT_AFTER_PURGE_ALL_CACHE = 'afterPurgeAllCache';

    /**
     * @inheritdoc
     */
    public function purgeUris(array $siteUris, callable $setProgressHandler = null, bool $queue = true): void
    {
        $event = new RefreshCacheEvent(['siteUris' => $siteUris]);
        $this->trigger(self::EVENT_BEFORE_PURGE_CACHE, $event);

        if (!$event->isValid) {
            return;
        }

        if ($queue) {
            CachePurgerHelper::addPurgerJob($siteUris, 'purgeUrisWithProgress');
        }
        else {
            $this->purgeUrisWithProgress($siteUris, $setProgressHandler);
        }

        if ($this->hasEventHandlers(self::EVENT_AFTER_PURGE_CACHE)) {
            $this->trigger(self::EVENT_AFTER_PURGE_CACHE, $event);
        }
    }

    /**
     * @inheritdoc
     */
    public function purgeSite(int $siteId, callable $setProgressHandler = null, bool $queue = true): void
    {
        $this->purgeUris(SiteUriHelper::getSiteUrisForSite($siteId), $setProgressHandler, $queue);
    }

    /**
     * @inheritdoc
     */
    public function purgeAll(callable $setProgressHandler = null, bool $queue = true): void
    {
        $event = new RefreshCacheEvent();
        $this->trigger(self::EVENT_BEFORE_PURGE_ALL_CACHE, $event);

        if (!$event->isValid) {
            return;
        }

        $sites = Craft::$app->getSites()->getAllSites();

        foreach ($sites as $site) {
            $this->purgeSite($site->id, $setProgressHandler, $queue);
        }

        if ($this->hasEventHandlers(self::EVENT_AFTER_PURGE_ALL_CACHE)) {
            $this->trigger(self::EVENT_AFTER_PURGE_ALL_CACHE, $event);
        }
    }

    /**
     * Purge site URIs with progress.
     */
    public function purgeUrisWithProgress(array $siteUris, callable $setProgressHandler = null): void
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
