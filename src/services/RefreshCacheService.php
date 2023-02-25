<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\services;

use Craft;
use craft\base\Component;
use craft\base\Element;
use craft\base\ElementInterface;
use craft\elements\Asset;
use craft\elements\Entry;
use craft\elements\GlobalSet;
use craft\helpers\Db;
use craft\helpers\ElementHelper;
use craft\helpers\Queue;
use DateTime;
use putyourlightson\blitz\behaviors\ElementChangedBehavior;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\events\RefreshCacheEvent;
use putyourlightson\blitz\events\RefreshCacheTagsEvent;
use putyourlightson\blitz\events\RefreshElementEvent;
use putyourlightson\blitz\events\RefreshSiteCacheEvent;
use putyourlightson\blitz\helpers\CacheGeneratorHelper;
use putyourlightson\blitz\helpers\CachePurgerHelper;
use putyourlightson\blitz\helpers\DeployerHelper;
use putyourlightson\blitz\helpers\ElementTypeHelper;
use putyourlightson\blitz\helpers\SiteUriHelper;
use putyourlightson\blitz\jobs\RefreshCacheJob;
use putyourlightson\blitz\models\RefreshDataModel;
use putyourlightson\blitz\models\SiteUriModel;
use putyourlightson\blitz\records\CacheRecord;
use putyourlightson\blitz\records\ElementExpiryDateRecord;
use putyourlightson\blitz\records\SsiIncludeCacheRecord;
use yii\db\ActiveQuery;

/**
 * This class is responsible for keeping the cache fresh.
 * When one or more cacheable element are updated, they are added to the `$elements` array.
 * If `$batchMode` is false then the `refresh()` method is called immediately,
 * otherwise it is triggered by a resave event.
 * The `refresh()` method creates a `RefreshCacheJob` so that refreshing happens asynchronously.
 *
 * @property SiteUriModel[] $allSiteUris
 */
class RefreshCacheService extends Component
{
    /**
     * @event RefreshElementEvent
     */
    public const EVENT_BEFORE_ADD_ELEMENT = 'beforeAddElement';

    /**
     * @event RefreshElementEvent
     */
    public const EVENT_AFTER_ADD_ELEMENT = 'afterAddElement';

    /**
     * @event RefreshCacheEvent
     */
    public const EVENT_BEFORE_REFRESH_CACHE = 'beforeRefreshCache';

    /**
     * @event RefreshCacheEvent
     */
    public const EVENT_AFTER_REFRESH_CACHE = 'afterRefreshCache';

    /**
     * @event RefreshCacheTagsEvent
     */
    public const EVENT_BEFORE_REFRESH_CACHE_TAGS = 'beforeRefreshCacheTags';

    /**
     * @event RefreshCacheTagsEvent
     */
    public const EVENT_AFTER_REFRESH_CACHE_TAGS = 'afterRefreshCacheTags';

    /**
     * @event RefreshCacheEvent
     */
    public const EVENT_BEFORE_REFRESH_ALL_CACHE = 'beforeRefreshAllCache';

    /**
     * @event RefreshCacheEvent
     */
    public const EVENT_AFTER_REFRESH_ALL_CACHE = 'afterRefreshAllCache';

    /**
     * @event RefreshSiteCacheEvent
     */
    public const EVENT_BEFORE_REFRESH_SITE_CACHE = 'beforeRefreshSiteCache';

    /**
     * @event RefreshSiteCacheEvent
     */
    public const EVENT_AFTER_REFRESH_SITE_CACHE = 'afterRefreshSiteCache';

    /**
     * @var bool
     */
    public bool $batchMode = false;

    /**
     * @var RefreshDataModel
     */
    public RefreshDataModel $refreshData;

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();

        $this->reset();
    }

    /**
     * Resets the component, so it can be used multiple times in the same request.
     */
    public function reset(): void
    {
        $this->refreshData = new RefreshDataModel();
    }

    /**
     * Returns site URIs from SSI includes related to the provided site URIs.
     *
     * @param SiteUriModel[] $siteUris
     * @return SiteUriModel[]
     */
    public function getSsiIncludeSiteUris(array $siteUris): array
    {
        $indexes = [];
        foreach ($siteUris as $siteUri) {
            $queryString = parse_url($siteUri->uri, PHP_URL_QUERY);
            parse_str($queryString, $queryStringParams);
            $index = $queryStringParams['index'] ?? null;

            if ($index !== null) {
                $indexes[] = $index;
            }
        }

        $cacheIds = SsiIncludeCacheRecord::find()
            ->select('cacheId')
            ->innerJoinWith([
                'include' => function(ActiveQuery $query) use ($indexes) {
                    $query->where(['index' => $indexes]);
                },
            ], false)
            ->column();

        return SiteUriHelper::getCachedSiteUris($cacheIds);
    }

    /**
     * Adds cache IDs to refresh.
     */
    public function addCacheIds(array $cacheIds): void
    {
        $this->refreshData->addCacheIds($cacheIds);
    }

    /**
     * Adds element IDs to refresh.
     */
    public function addElementIds(string $elementType, array $elementIds): void
    {
        $this->refreshData->addElementIds($elementType, $elementIds);
    }

    /**
     * Adds an element to refresh.
     */
    public function addElement(ElementInterface $element): void
    {
        // Don't proceed if not an actual element
        if (!($element instanceof Element)) {
            return;
        }

        // Don't proceed if the element is an asset that is being indexed
        if ($element instanceof Asset && $element->getScenario() == Asset::SCENARIO_INDEX) {
            return;
        }

        // Don't proceed if propagating
        if ($element->propagating) {
            return;
        }

        // Refresh the entire cache if this is a global set since they are populated on every request
        if ($element instanceof GlobalSet) {
            if (Blitz::$plugin->settings->refreshCacheAutomaticallyForGlobals) {
                $this->refreshAll();
            }

            return;
        }

        $elementType = $element::class;

        // Don't proceed if not a cacheable element type
        if (!ElementTypeHelper::getIsCacheableElementType($elementType)) {
            return;
        }

        // Don't proceed if element is a draft or revision
        if (ElementHelper::isDraftOrRevision($element)) {
            return;
        }

        // If the element has the element changed behavior
        /** @var ElementChangedBehavior|null $elementChanged */
        $elementChanged = $element->getBehavior(ElementChangedBehavior::BEHAVIOR_NAME);

        if ($elementChanged !== null) {
            // Don't proceed if element has not changed (and the config setting allows)
            if (!Blitz::$plugin->settings->refreshCacheWhenElementSavedUnchanged
                && !$elementChanged->getHasChanged()
            ) {
                return;
            }

            // Don't proceed if the element status has not changed and is not refreshable (and the config setting allows). Refreshing pending (https://github.com/putyourlightson/craft-blitz/issues/422) and expired (https://github.com/putyourlightson/craft-blitz/issues/267) elements is necessary to clear cached pages.
            if (!Blitz::$plugin->settings->refreshCacheWhenElementSavedNotLive
                && !$elementChanged->getHasStatusChanged()
                && !$elementChanged->getHasRefreshableStatus()
            ) {
                return;
            }
        }

        $event = new RefreshElementEvent(['element' => $element]);
        $this->trigger(self::EVENT_BEFORE_ADD_ELEMENT, $event);

        if (!$event->isValid) {
            return;
        }

        // Add element to refresh data
        $this->refreshData->addElement($element, $elementChanged);

        // Add element expiry dates
        $this->addElementExpiryDates($element);

        if ($this->hasEventHandlers(self::EVENT_AFTER_ADD_ELEMENT)) {
            $this->trigger(self::EVENT_AFTER_ADD_ELEMENT, $event);
        }

        // If batch mode is on then the refresh will be triggered later
        if ($this->batchMode === false) {
            $this->refresh();
        }
    }

    /**
     * Adds expiry dates for a given element.
     */
    public function addElementExpiryDates(Element $element): void
    {
        $expiryDate = null;
        $now = new DateTime();

        if (!empty($element->postDate) && $element->postDate > $now) {
            $expiryDate = $element->postDate;
        } elseif (!empty($element->expiryDate) && $element->expiryDate > $now) {
            $expiryDate = $element->expiryDate;
        }

        if ($expiryDate !== null) {
            $this->addElementExpiryDate($element, $expiryDate);
        }
    }

    /**
     * Adds or updates an expiry date for a given element.
     */
    public function addElementExpiryDate(Element $element, DateTime $expiryDate): void
    {
        $expiryDate = Db::prepareDateForDb($expiryDate);

        Craft::$app->getDb()->createCommand()
            ->upsert(
                ElementExpiryDateRecord::tableName(),
                [
                    'elementId' => $element->id,
                    'expiryDate' => $expiryDate,
                ],
                [
                    'expiryDate' => $expiryDate,
                ],
                [],
                false
            )
            ->execute();
    }

    /**
     * Adds an expiry date for the given cache IDs.
     *
     * @param int[] $cacheIds
     */
    public function expireCacheIds(array $cacheIds, DateTime $expiryDate = null): void
    {
        if (empty($cacheIds)) {
            return;
        }

        if ($expiryDate === null) {
            $expiryDate = new DateTime();
        }

        $expiryDate = Db::prepareDateForDb($expiryDate);

        Craft::$app->getDb()->createCommand()
            ->update(
                CacheRecord::tableName(),
                ['expiryDate' => $expiryDate],
                ['id' => $cacheIds],
                [],
                false
            )
            ->execute();
    }

    /**
     * Generates element expiry dates.
     */
    public function generateExpiryDates(string $elementType = null): void
    {
        if ($elementType === null) {
            $elementType = Entry::class;
        }

        $now = Db::prepareDateForDb(new DateTime());

        /** @var Element $elementType */
        /** @var Element[] $elements */
        $elements = $elementType::find()
            ->where([
                'or',
                ['>', 'postDate', $now],
                ['>', 'expiryDate', $now],
            ])
            ->status(null)
            ->all();

        foreach ($elements as $element) {
            $this->addElementExpiryDates($element);
        }
    }

    /**
     * Refreshes the cache.
     */
    public function refresh(bool $forceClear = false, bool $forceGenerate = false): void
    {
        if ($this->refreshData->isEmpty()) {
            return;
        }

        $job = new RefreshCacheJob([
            'data' => $this->refreshData->data,
            'forceClear' => $forceClear,
            'forceGenerate' => $forceGenerate,
        ]);
        Queue::push($job, Blitz::$plugin->settings->refreshCacheJobPriority);

        $this->reset();
    }

    /**
     * Refreshes site URIs.
     *
     * @param SiteUriModel[] $siteUris
     * @param SiteUriModel[] $purgeSiteUris
     */
    public function refreshSiteUris(array $siteUris, array $purgeSiteUris = [], bool $forceClear = false, bool $forceGenerate = false): void
    {
        $event = new RefreshCacheEvent(['siteUris' => $siteUris]);
        $this->trigger(self::EVENT_BEFORE_REFRESH_CACHE, $event);

        if (!$event->isValid) {
            return;
        }

        $siteUris = $event->siteUris;

        $this->_refreshSiteUris($siteUris, $purgeSiteUris, $forceClear, $forceGenerate);

        if ($this->hasEventHandlers(self::EVENT_AFTER_REFRESH_CACHE)) {
            $this->trigger(self::EVENT_AFTER_REFRESH_CACHE, $event);
        }
    }

    /**
     * Refreshes a site URI if it has expired.
     */
    public function refreshSiteUriIfExpired(SiteUriModel $siteUri): void
    {
        $now = Db::prepareDateForDb(new DateTime());

        // Get the cache IDs of expired site URIs
        $cacheIds = CacheRecord::find()
            ->select('id')
            ->where($siteUri->toArray())
            ->andWhere(['<', 'expiryDate', $now])
            ->column();

        if (empty($cacheIds)) {
            return;
        }

        $this->addCacheIds($cacheIds);

        // Force clear the cache if it will not be regenerated.
        $forceClear = !Blitz::$plugin->settings->generateOnRefresh();

        $this->refresh($forceClear);
    }

    /**
     * Refreshes the entire cache, respecting the “Refresh Mode”.
     */
    public function refreshAll(): void
    {
        $event = new RefreshCacheEvent();
        $this->trigger(self::EVENT_BEFORE_REFRESH_ALL_CACHE, $event);

        if (!$event->isValid) {
            return;
        }

        $generateOnRefresh = Blitz::$plugin->settings->generateOnRefresh();

        // Get site URIs to generate before flushing the cache
        if ($generateOnRefresh) {
            $siteUris = array_merge(
                SiteUriHelper::getAllSiteUris(),
                Blitz::$plugin->settings->getCustomSiteUris(),
            );
        }

        if (Blitz::$plugin->settings->clearOnRefresh()) {
            // Release jobs, since we're anyway clearing the cache
            $this->releaseJobs();

            Blitz::$plugin->clearCache->clearAll();
            Blitz::$plugin->flushCache->flushAll(true);
            Blitz::$plugin->cachePurger->purgeAll();
        }

        if ($generateOnRefresh) {
            Blitz::$plugin->cacheGenerator->generateUris($siteUris);
            Blitz::$plugin->deployer->deployUris($siteUris);

            if (Blitz::$plugin->settings->purgeAfterGenerate()) {
                Blitz::$plugin->cachePurger->purgeUris($siteUris);
            }
        }

        if ($this->hasEventHandlers(self::EVENT_AFTER_REFRESH_ALL_CACHE)) {
            $this->trigger(self::EVENT_AFTER_REFRESH_ALL_CACHE, $event);
        }
    }

    /**
     * Refreshes a site.
     */
    public function refreshSite(int $siteId): void
    {
        $event = new RefreshSiteCacheEvent(['siteId' => $siteId]);
        $this->trigger(self::EVENT_BEFORE_REFRESH_SITE_CACHE, $event);

        if (!$event->isValid) {
            return;
        }

        // Get site URIs to generate before flushing the cache
        $siteUris = SiteUriHelper::getSiteUrisForSite($siteId, true);

        foreach (Blitz::$plugin->settings->getCustomSiteUris() as $customSiteUri) {
            if ($customSiteUri['siteId'] == $siteId) {
                $siteUris[] = $customSiteUri;
            }
        }

        $this->_refreshSiteUris($siteUris);

        if ($this->hasEventHandlers(self::EVENT_AFTER_REFRESH_SITE_CACHE)) {
            $this->trigger(self::EVENT_AFTER_REFRESH_SITE_CACHE, $event);
        }
    }

    /**
     * Refreshes expired cache.
     */
    public function refreshExpiredCache(): void
    {
        $this->batchMode = true;
        $now = Db::prepareDateForDb(new DateTime());

        // Check for expired caches to invalidate
        $cacheIds = CacheRecord::find()
            ->select('id')
            ->where(['<', 'expiryDate', $now])
            ->column();

        $this->addCacheIds($cacheIds);

        // Check for expired elements to invalidate
        /** @var ElementExpiryDateRecord[] $elementExpiryDates */
        $elementExpiryDates = ElementExpiryDateRecord::find()
            ->where(['<', 'expiryDate', $now])
            ->all();

        $elementsService = Craft::$app->getElements();

        foreach ($elementExpiryDates as $elementExpiryDate) {
            $element = $elementsService->getElementById($elementExpiryDate->elementId, null, '*');

            // This should happen before invalidating the element so that other expiry dates will be saved
            $elementExpiryDate->delete();

            if ($element !== null) {
                $this->addElement($element);
            }
        }

        // Force clear the cache if it will not be regenerated.
        $forceClear = !Blitz::$plugin->settings->generateOnRefresh();

        $this->refresh($forceClear);
    }

    /**
     * Refreshes cached URLs.
     *
     * @param string[] $urls
     */
    public function refreshCachedUrls(array $urls): void
    {
        // Get site URIs from URLs
        $siteUris = SiteUriHelper::getSiteUrisFromUrls($urls);

        $this->refreshSiteUris($siteUris);
    }

    /**
     * Refreshes cache tags.
     *
     * @param string[] $tags
     */
    public function refreshCacheTags(array $tags): void
    {
        $event = new RefreshCacheTagsEvent(['tags' => $tags]);
        $this->trigger(self::EVENT_BEFORE_REFRESH_CACHE_TAGS, $event);

        if (!$event->isValid) {
            return;
        }

        $tags = $event->tags;

        // Get cache IDs to invalidate
        $cacheIds = Blitz::$plugin->cacheTags->getCacheIds($tags);

        $this->addCacheIds($cacheIds);

        $this->refresh(true);

        if ($this->hasEventHandlers(self::EVENT_AFTER_REFRESH_CACHE_TAGS)) {
            $this->trigger(self::EVENT_AFTER_REFRESH_CACHE_TAGS, $event);
        }
    }

    /**
     * Releases generator, deployer and purger jobs.
     */
    public function releaseJobs(): void
    {
        CacheGeneratorHelper::releaseGeneratorJobs();
        DeployerHelper::releaseDeployerJobs();
        CachePurgerHelper::releasePurgerJobs();
    }

    /**
     * Refreshes site URIs.
     *
     * @param SiteUriModel[] $siteUris
     * @param SiteUriModel[] $purgeSiteUris
     */
    private function _refreshSiteUris(array $siteUris, array $purgeSiteUris = [], bool $forceClear = false, bool $forceGenerate = false): void
    {
        $purgeableSiteUris = array_merge($siteUris, $purgeSiteUris);

        // If SSI is enabled, merge site URIs from SSI includes into purgeable site URIs.
        if (Blitz::$plugin->settings->ssiEnabled) {
            $purgeableSiteUris = array_merge($purgeableSiteUris, $this->getSsiIncludeSiteUris($siteUris));
        }

        if (Blitz::$plugin->settings->clearOnRefresh($forceClear)) {
            Blitz::$plugin->clearCache->clearUris($siteUris);
            Blitz::$plugin->cachePurger->purgeUris($purgeableSiteUris);
        }

        if (Blitz::$plugin->settings->generateOnRefresh($forceGenerate)) {
            Blitz::$plugin->cacheGenerator->generateUris($siteUris);
            Blitz::$plugin->deployer->deployUris($siteUris);
        }

        if (Blitz::$plugin->settings->purgeAfterGenerate($forceClear, $forceGenerate)) {
            Blitz::$plugin->cachePurger->purgeUris($purgeableSiteUris);
        }
    }
}
