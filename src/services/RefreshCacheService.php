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
use DateTime;
use putyourlightson\blitz\behaviors\ElementChangedBehavior;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\events\RefreshCacheEvent;
use putyourlightson\blitz\events\RefreshCacheTagsEvent;
use putyourlightson\blitz\events\RefreshElementEvent;
use putyourlightson\blitz\events\RefreshSiteCacheEvent;
use putyourlightson\blitz\helpers\ElementTypeHelper;
use putyourlightson\blitz\helpers\SiteUriHelper;
use putyourlightson\blitz\jobs\RefreshCacheJob;
use putyourlightson\blitz\models\SiteUriModel;
use putyourlightson\blitz\records\CacheRecord;
use putyourlightson\blitz\records\ElementCacheRecord;
use putyourlightson\blitz\records\ElementExpiryDateRecord;
use putyourlightson\blitz\records\ElementQueryRecord;
use yii\base\NotSupportedException;
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
     * @var int[]
     */
    public array $cacheIds = [];

    /**
     * @var array
     */
    public array $elements = [];

    /**
     * Resets the component, so it can be used multiple times in the same request.
     */
    public function reset()
    {
        $this->cacheIds = [];
        $this->elements = [];
    }

    /**
     * Returns cache IDs given an array of element IDs.
     *
     * @param int[] $elementIds
     * @return int[]
     */
    public function getElementCacheIds(array $elementIds): array
    {
        /** @var int[] $ids */
        /** @noinspection PhpUnnecessaryLocalVariableInspection */
        $ids = ElementCacheRecord::find()
            ->select('cacheId')
            ->where(['elementId' => $elementIds])
            ->groupBy('cacheId')
            ->column();

        return $ids;
    }

    /**
     * Returns element queries of the provided element type that can be joined
     * with the provided source IDs, ignoring the provided cache IDs.
     *
     * @param int[] $sourceIds
     * @param int[] $ignoreCacheIds
     * @return ElementQueryRecord[]
     */
    public function getElementTypeQueries(string $elementType, array $sourceIds, array $ignoreCacheIds): array
    {
        // Get element query records without eager loading
        /** @var ElementQueryRecord[] $records */
        /** @noinspection PhpUnnecessaryLocalVariableInspection */
        $records = ElementQueryRecord::find()
            ->where(['type' => $elementType])
            ->innerJoinWith([
                'elementQuerySources' => function(ActiveQuery $query) use ($sourceIds) {
                    $query->where(['sourceId' => $sourceIds])
                        ->orWhere(['sourceId' => null]);
                },
            ], false)
            ->innerJoinWith([
                'elementQueryCaches' => function(ActiveQuery $query) use ($ignoreCacheIds) {
                    $query->where(['not', ['cacheId' => $ignoreCacheIds]]);
                },
            ], false)
            ->all();

        return $records;
    }

    /**
     * Adds cache IDs to refresh.
     */
    public function addCacheIds(array $cacheIds)
    {
        $this->cacheIds = array_unique(array_merge($this->cacheIds, $cacheIds));
    }

    /**
     * Adds element IDs to refresh.
     */
    public function addElementIds(string $elementType, array $elementIds)
    {
        $this->elements[$elementType] = $this->elements[$elementType] ?? [
            'elementIds' => [],
            'sourceIds' => [],
        ];

        $this->elements[$elementType]['elementIds'] = array_unique(array_merge($this->elements[$elementType]['elementIds'], $elementIds));
    }

    /**
     * Adds an element to refresh.
     */
    public function addElement(ElementInterface $element)
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

        $elementType = get_class($element);

        // Don't proceed if not a cacheable element type
        if (!ElementTypeHelper::getIsCacheableElementType($elementType)) {
            return;
        }

        // Don't proceed if element is a draft or revision
        if (ElementHelper::isDraftOrRevision($element)) {
            return;
        }

        $this->elements[$elementType] = $this->elements[$elementType] ?? [
            'elementIds' => [],
            'sourceIds' => [],
        ];

        // Don't proceed if element has already been added
        if (in_array($element->getId(), $this->elements[$elementType]['elementIds'])) {
            return;
        }

        // If the element has the element changed behavior
        /** @var ElementChangedBehavior|null $elementChanged */
        $elementChanged = $element->getBehavior(ElementChangedBehavior::BEHAVIOR_NAME);

        if ($elementChanged !== null) {
            // Don't proceed if element has not changed (and the config setting allows)
            if (!Blitz::$plugin->settings->refreshCacheWhenElementSavedUnchanged && !$elementChanged->getHasChanged()) {
                return;
            }

            // Don't proceed if element status has not changed and is not live or expired (and the config setting allows). Refreshing expired elements is necessary to clear cached pages (https://github.com/putyourlightson/craft-blitz/issues/267).
            if (!Blitz::$plugin->settings->refreshCacheWhenElementSavedNotLive
                && !$elementChanged->getHasStatusChanged()
                && !$elementChanged->getHasLiveOrExpiredStatus()
            ) {
                return;
            }
        }

        $event = new RefreshElementEvent(['element' => $element]);
        $this->trigger(self::EVENT_BEFORE_ADD_ELEMENT, $event);

        if (!$event->isValid) {
            return;
        }

        // Add element
        $this->elements[$elementType]['elementIds'][] = $element->id;

        // Add source ID
        $sourceIdAttribute = ElementTypeHelper::getSourceIdAttribute($elementType);

        if ($sourceIdAttribute !== null) {
            $sourceId = $element->{$sourceIdAttribute};

            if (!in_array($sourceId, $this->elements[$elementType]['sourceIds'])) {
                $this->elements[$elementType]['sourceIds'][] = $sourceId;
            }
        }

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
    public function addElementExpiryDates(Element $element)
    {
        $expiryDate = null;
        $now = new DateTime();

        if (!empty($element->postDate) && $element->postDate > $now) {
            $expiryDate = $element->postDate;
        }
        elseif (!empty($element->expiryDate) && $element->expiryDate > $now) {
            $expiryDate = $element->expiryDate;
        }

        if ($expiryDate !== null) {
            $this->addElementExpiryDate($element, $expiryDate);
        }
    }

    /**
     * Adds an expiry date for a given element.
     */
    public function addElementExpiryDate(Element $element, DateTime $expiryDate)
    {
        $expiryDate = Db::prepareDateForDb($expiryDate);

        /** @var ElementExpiryDateRecord|null $elementExpiryDateRecord */
        $elementExpiryDateRecord = ElementExpiryDateRecord::find()
            ->where(['elementId' => $element->id])
            ->one();

        if ($elementExpiryDateRecord !== null && $elementExpiryDateRecord->expiryDate < $expiryDate) {
            $expiryDate = $elementExpiryDateRecord->expiryDate;
        }

        /** @noinspection MissedFieldInspection */
        Craft::$app->getDb()->createCommand()
            ->upsert(ElementExpiryDateRecord::tableName(), [
                    'elementId' => $element->id,
                    'expiryDate' => $expiryDate,
                ],
                ['expiryDate' => $expiryDate],
                [],
                false)
            ->execute();
    }

    /**
     * Adds an expiry date for the given cache IDs.
     *
     * @param int[] $cacheIds
     */
    public function expireCacheIds(array $cacheIds, DateTime $expiryDate = null)
    {
        if (empty($cacheIds)) {
            return;
        }

        if ($expiryDate === null) {
            $expiryDate = new DateTime();
        }

        $expiryDate = Db::prepareDateForDb($expiryDate);

        Craft::$app->getDb()->createCommand()
            ->update(CacheRecord::tableName(),
                ['expiryDate' => $expiryDate],
                ['id' => $cacheIds],
                [],
                false)
            ->execute();
    }

    /**
     * Generates element expiry dates.
     */
    public function generateExpiryDates(string $elementType = null)
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
    public function refresh(bool $forceClear = false, bool $forceGenerate = false)
    {
        if (empty($this->cacheIds) && empty($this->elements)) {
            return;
        }

        $refreshCacheJob = new RefreshCacheJob([
            'cacheIds' => $this->cacheIds,
            'elements' => $this->elements,
            'forceClear' => $forceClear,
            'forceGenerate' => $forceGenerate,
        ]);

        $queue = Craft::$app->getQueue();

        try {
            $queue->priority(Blitz::$plugin->settings->refreshCacheJobPriority)
                ->push($refreshCacheJob);
        }
        /** @noinspection PhpRedundantCatchClauseInspection */
        catch (NotSupportedException) {
            // The queue probably doesn't support custom push priorities. Try again without one.
            $queue->push($refreshCacheJob);
        }

        $this->reset();
    }

    /**
     * Refreshes site URIs.
     *
     * @param SiteUriModel[] $siteUris
     */
    public function refreshSiteUris(array $siteUris, bool $forceClear = false, bool $forceGenerate = false)
    {
        $event = new RefreshCacheEvent(['siteUris' => $siteUris]);
        $this->trigger(self::EVENT_BEFORE_REFRESH_CACHE, $event);

        if (!$event->isValid) {
            return;
        }

        $siteUris = $event->siteUris;

        $this->_refreshSiteUris($siteUris, $forceClear, $forceGenerate);

        if ($this->hasEventHandlers(self::EVENT_AFTER_REFRESH_CACHE)) {
            $this->trigger(self::EVENT_AFTER_REFRESH_CACHE, $event);
        }
    }

    /**
     * Refreshes a site URI if it has expired.
     */
    public function refreshSiteUriIfExpired(SiteUriModel $siteUri)
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
        $this->refresh(false, true);
    }

    /**
     * Refreshes the entire cache.
     */
    public function refreshAll()
    {
        $event = new RefreshCacheEvent();
        $this->trigger(self::EVENT_BEFORE_REFRESH_ALL_CACHE, $event);

        if (!$event->isValid) {
            return;
        }

        // Get site URIs to generate before flushing the cache
        $siteUris = array_merge(
            SiteUriHelper::getAllSiteUris(),
            Blitz::$plugin->settings->customSiteUris
        );

        if (Blitz::$plugin->settings->clearOnRefresh()) {
            Blitz::$plugin->clearCache->clearAll();
            Blitz::$plugin->flushCache->flushAll();
        }

        if (Blitz::$plugin->settings->generateOnRefresh()) {
            Blitz::$plugin->cacheGenerator->generateUris($siteUris);
            Blitz::$plugin->deployer->deployUris($siteUris);
        }

        if (Blitz::$plugin->settings->purgeOnRefresh()) {
            Blitz::$plugin->cachePurger->purgeUris($siteUris);
        }

        if ($this->hasEventHandlers(self::EVENT_AFTER_REFRESH_ALL_CACHE)) {
            $this->trigger(self::EVENT_AFTER_REFRESH_ALL_CACHE, $event);
        }
    }

    /**
     * Refreshes a site.
     */
    public function refreshSite(int $siteId)
    {
        $event = new RefreshSiteCacheEvent(['siteId' => $siteId]);
        $this->trigger(self::EVENT_BEFORE_REFRESH_SITE_CACHE, $event);

        if (!$event->isValid) {
            return;
        }

        // Get site URIs to generate before flushing the cache
        $siteUris = SiteUriHelper::getSiteUrisForSite($siteId, true);

        foreach (Blitz::$plugin->settings->customSiteUris as $customSiteUri) {
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
    public function refreshExpiredCache()
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

        $this->refresh(true);
    }

    /**
     * Refreshes cached URLs.
     *
     * @param string[] $urls
     */
    public function refreshCachedUrls(array $urls)
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
    public function refreshCacheTags(array $tags)
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
     * Refreshes site URIs.
     *
     * @param SiteUriModel[] $siteUris
     */
    private function _refreshSiteUris(array $siteUris, bool $forceClear = false, bool $forceGenerate = false)
    {
        if (Blitz::$plugin->settings->clearOnRefresh() || $forceClear) {
            Blitz::$plugin->clearCache->clearUris($siteUris);
        }

        if (Blitz::$plugin->settings->generateOnRefresh() || $forceGenerate) {
            Blitz::$plugin->cacheGenerator->generateUris($siteUris);
            Blitz::$plugin->deployer->deployUris($siteUris);
        }

        if (Blitz::$plugin->settings->purgeOnRefresh() || $forceGenerate) {
            Blitz::$plugin->cachePurger->purgeUris($siteUris);
        }
    }
}
