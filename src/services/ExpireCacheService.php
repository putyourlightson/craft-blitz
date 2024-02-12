<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\services;

use Craft;
use craft\base\Component;
use craft\helpers\Db;
use DateTime;
use putyourlightson\blitz\events\RefreshCacheEvent;
use putyourlightson\blitz\helpers\SiteUriHelper;
use putyourlightson\blitz\models\SiteUriModel;
use putyourlightson\blitz\records\CacheRecord;

/**
 * @property-read int[] $expiredCacheIds
 *
 * @since 4.8.0
 */
class ExpireCacheService extends Component
{
    /**
     * @event RefreshCacheEvent
     */
    public const EVENT_BEFORE_EXPIRE_CACHE = 'beforeExpireCache';

    /**
     * @event RefreshCacheEvent
     */
    public const EVENT_AFTER_EXPIRE_CACHE = 'afterExpireCache';

    /**
     * @event RefreshCacheEvent
     */
    public const EVENT_BEFORE_EXPIRE_ALL_CACHE = 'beforeExpireAllCache';

    /**
     * @event RefreshCacheEvent
     */
    public const EVENT_AFTER_EXPIRE_ALL_CACHE = 'afterExpireAllCache';

    /**
     * Returns expired cache IDs with the provided condition.
     *
     * @return int[]
     */
    public function getExpiredCacheIds(): array
    {
        return CacheRecord::find()
            ->select('id')
            ->where(['<=', 'expiryDate', Db::prepareDateForDb('now')])
            ->column();
    }

    /**
     * Returns an expired cache ID with the provided site URI.
     */
    public function getExpiredCacheId(SiteUriModel $siteUri): int|false
    {
        return CacheRecord::find()
            ->select('id')
            ->where(['<=', 'expiryDate', Db::prepareDateForDb('now')])
            ->andWhere($siteUri->toArray())
            ->scalar();
    }

    /**
     * Adds an expiry date to the cache given an array of site URIs.
     *
     * @param SiteUriModel[] $siteUris
     */
    public function expireUris(array $siteUris, DateTime $expiryDate = null): void
    {
        $event = new RefreshCacheEvent(['siteUris' => $siteUris]);
        $this->trigger(self::EVENT_BEFORE_EXPIRE_CACHE, $event);

        if (!$event->isValid) {
            return;
        }

        $cacheIds = SiteUriHelper::getCacheIdsFromSiteUris($siteUris);
        $this->expireCache(['id' => $cacheIds], $expiryDate);

        if ($this->hasEventHandlers(self::EVENT_AFTER_EXPIRE_CACHE)) {
            $this->trigger(self::EVENT_AFTER_EXPIRE_CACHE, $event);
        }
    }

    /**
     * Adds an expiry date to the cache for a given site.
     */
    public function expireSite(int $siteId, DateTime $expiryDate = null): void
    {
        $siteUris = SiteUriHelper::getSiteUrisForSite($siteId);

        $this->expireUris($siteUris, $expiryDate);
    }

    /**
     * Adds an expiry date to the entire cache.
     */
    public function expireAll(DateTime $expiryDate = null): void
    {
        $event = new RefreshCacheEvent();
        $this->trigger(self::EVENT_BEFORE_EXPIRE_ALL_CACHE, $event);

        if (!$event->isValid) {
            return;
        }

        $this->expireCache([], $expiryDate);

        if ($this->hasEventHandlers(self::EVENT_AFTER_EXPIRE_ALL_CACHE)) {
            $this->trigger(self::EVENT_AFTER_EXPIRE_ALL_CACHE, $event);
        }
    }

    /**
     * Adds an expiry date to the cache based on the given condition.
     */
    private function expireCache(array $condition, DateTime $expiryDate = null): void
    {
        if ($expiryDate === null) {
            $expiryDate = new DateTime();
        }

        $expiryDate = Db::prepareDateForDb($expiryDate);

        Craft::$app->getDb()->createCommand()
            ->update(
                CacheRecord::tableName(),
                ['expiryDate' => $expiryDate],
                $condition,
                [],
                false
            )
            ->execute();
    }
}
