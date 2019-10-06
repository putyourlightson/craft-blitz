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
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\events\RefreshCacheEvent;
use putyourlightson\blitz\helpers\ElementTypeHelper;
use putyourlightson\blitz\helpers\SiteUriHelper;
use putyourlightson\blitz\jobs\RefreshCacheJob;
use putyourlightson\blitz\models\SiteUriModel;
use putyourlightson\blitz\records\CacheRecord;
use putyourlightson\blitz\records\ElementCacheRecord;
use putyourlightson\blitz\records\ElementExpiryDateRecord;
use putyourlightson\blitz\records\ElementQueryRecord;
use yii\db\ActiveQuery;

/**
 * @property SiteUriModel[] $allSiteUris
 */
class RefreshCacheService extends Component
{
    // Constants
    // =========================================================================

    /**
     * @event RefreshCacheEvent
     */
    const EVENT_BEFORE_REFRESH_CACHE = 'beforeRefreshCache';

    /**
     * @event RefreshCacheEvent
     */
    const EVENT_AFTER_REFRESH_CACHE = 'afterRefreshCache';

    // Properties
    // =========================================================================

    /**
     * @var bool
     */
    public $batchMode = false;

    /**
     * @var int[]
     */
    private $_cacheIds = [];

    /**
     * @var int[]
     */
    private $_elementIds = [];

    /**
     * @var string[]
     */
    private $_elementTypes = [];

    // Public Methods
    // =========================================================================

    /**
     * Returns an array of unique cache IDs given an array of elements and cache IDs.
     *
     * @param int[] $elementIds
     * @param int[] $cacheIds
     *
     * @return int[]
     */
    public function getElementCacheIds(array $elementIds, array $cacheIds = []): array
    {
        $elementCacheIds = ElementCacheRecord::find()
            ->select('cacheId')
            ->where(['elementId' => $elementIds])
            ->andWhere(['not', ['cacheId' => $cacheIds]])
            ->groupBy('cacheId')
            ->column();

        return array_merge($cacheIds, $elementCacheIds);
    }

    /**
     * Returns cache IDs from entry queries of the provided element types that
     * contain the provided element IDs, ignoring the provided cache IDs.
     *
     * @param string[] $elementTypes
     * @param int[] $ignoreCacheIds
     *
     * @return ElementQueryRecord[]
     */
    public function getElementTypeQueries(array $elementTypes, array $ignoreCacheIds): array
    {
        // Get element query records of the provided element types without the cache IDs and without eager loading
        return ElementQueryRecord::find()
            ->select(['id', 'type', 'params'])
            ->where(['type' => $elementTypes])
            ->innerJoinWith([
                'elementQueryCaches' => function(ActiveQuery $query) use ($ignoreCacheIds) {
                    $query->where(['not', ['cacheId' => $ignoreCacheIds]]);
                }
            ], false)
            ->all();
    }

    /**
     * Adds cache IDs to refresh given an element.
     *
     * @param ElementInterface $element
     */
    public function addCacheIds(ElementInterface $element)
    {
        $this->_cacheIds = $this->getElementCacheIds([$element->getId()], $this->_cacheIds);
    }

    /**
     * Adds an element to refresh.
     *
     * @param ElementInterface $element
     */
    public function addElement(ElementInterface $element)
    {
        // Don't proceed if not an Element
        if (!($element instanceof Element)) {
            return;
        }

        // Don't proceed if propagating
        if ($element->propagating) {
            return;
        }

        // Don't proceed if element is draft or revision
        if (ElementHelper::isDraftOrRevision($element)) {
            return;
        }

        // Don't proceed if this element is an asset that is being indexed
        if ($element instanceof Asset && $element->getScenario() == Asset::SCENARIO_INDEX) {
            return;
        }

        // Clear the entire cache if this is a global set element as they are populated on every request
        if ($element instanceof GlobalSet) {
            if (Blitz::$plugin->settings->clearCacheAutomaticallyForGlobals) {
                Blitz::$plugin->clearCache->clearAll();
            }

            if (Blitz::$plugin->settings->cachingEnabled
                && Blitz::$plugin->settings->warmCacheAutomatically
                && Blitz::$plugin->settings->warmCacheAutomaticallyForGlobals) {
                Blitz::$plugin->cacheWarmer->warmAll();
            }

            return;
        }

        $elementType = get_class($element);

        // Don't proceed if not a cacheable element type
        if (!ElementTypeHelper::getIsCacheableElementType($elementType)) {
            return;
        }

        // Cast ID to integer to ensure the strict type check below works
        $elementId = (int)$element->getId();

        // Don't proceed if element has already been added
        if (in_array($elementId, $this->_elementIds, true)) {
            return;
        }

        // Add element
        $this->_elementIds[] = $elementId;

        // Add element type
        if (!in_array($elementType, $this->_elementTypes, true)) {
            $this->_elementTypes[] = $elementType;
        }

        // Add element expiry dates
        $this->addElementExpiryDates($element);

        // If batch mode is on then the refresh will be triggered later
        if ($this->batchMode === false) {
            $this->refresh();
        }
    }

    /**
     * Adds expiry dates for a given element.
     *
     * @param ElementInterface $element
     */
    public function addElementExpiryDates(ElementInterface $element)
    {
        $expiryDate = null;
        $now = new DateTime();

        if (!empty($element->postDate) && $element->postDate > $now) {
            $expiryDate = $element->postDate;
        }
        else if (!empty($element->expiryDate) && $element->expiryDate > $now) {
            $expiryDate = $element->expiryDate;
        }

        if ($expiryDate !== null) {
            $this->addElementExpiryDate($element, $expiryDate);
        }
    }

    /**
     * Adds an expiry date for a given element.
     *
     * @param ElementInterface $element
     * @param DateTime $expiryDate
     */
    public function addElementExpiryDate(ElementInterface $element, DateTime $expiryDate)
    {
        $expiryDate = Db::prepareDateForDb($expiryDate);

        /** @var ElementExpiryDateRecord|null $elementExpiryDateRecord */
        $elementExpiryDateRecord = ElementExpiryDateRecord::find()
            ->where(['elementId' => $element->getId()])
            ->one();

        if ($elementExpiryDateRecord !== null && $elementExpiryDateRecord->expiryDate < $expiryDate) {
            $expiryDate = $elementExpiryDateRecord->expiryDate;
        }

        /** @noinspection MissedFieldInspection */
        Craft::$app->getDb()->createCommand()
            ->upsert(ElementExpiryDateRecord::tableName(), [
                    'elementId' => $element->getId(),
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
     * @param DateTime|null $expiryDate
     */
    public function expireCacheIds(array $cacheIds, DateTime $expiryDate = null)
    {
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
     *
     * @param string|null $elementType
     */
    public function generateExpiryDates(string $elementType = null)
    {
        if ($elementType === null) {
            $elementType = Entry::class;
        }

        $now = Db::prepareDateForDb(new DateTime());

        /** @var Element $elementType */
        $elements = $elementType::find()
            ->where([
                'or',
                ['>', 'postDate', $now],
                ['>', 'expiryDate', $now],
            ])
            ->anyStatus()
            ->all();

        foreach ($elements as $element) {
            $this->addElementExpiryDates($element);
        }
    }

    /**
     * Refreshes the cache.
     *
     * @param bool $forceClear
     */
    public function refresh(bool $forceClear = false)
    {
        if (empty($this->_cacheIds) && empty($this->_elementIds)) {
            return;
        }

        // Add job to queue with a priority
        Craft::$app->getQueue()
            ->priority(Blitz::$plugin->settings->refreshCacheJobPriority)
            ->push(new RefreshCacheJob([
                'cacheIds' => $this->_cacheIds,
                'elementIds' => $this->_elementIds,
                'elementTypes' => $this->_elementTypes,
                'clearCache' => (Blitz::$plugin->settings->clearCacheAutomatically || $forceClear),
            ]));
    }

    /**
     * Refreshes site URIs.
     *
     * @param SiteUriModel[] $siteUris
     */
    public function refreshSiteUris(array $siteUris)
    {
        $event = $this->onBeforeRefresh(['siteUris' => $siteUris]);

        if (!$event->isValid) {
            return;
        }

        $siteUris = $event->siteUris;

        Blitz::$plugin->flushCache->flushUris($siteUris);

        Blitz::$plugin->clearCache->clearUris($siteUris);

        // Warm and deploy if enabled
        if (Blitz::$plugin->settings->cachingEnabled && Blitz::$plugin->settings->warmCacheAutomatically) {
            Blitz::$plugin->cacheWarmer->warmUris($siteUris, Blitz::$plugin->cachePurger->warmCacheDelay);

            Blitz::$plugin->deployer->deployUris($siteUris, Blitz::$plugin->cachePurger->warmCacheDelay);
        }

        // Fire an 'afterRefreshCache' event
        if ($this->hasEventHandlers(self::EVENT_AFTER_REFRESH_CACHE)) {
            $this->trigger(self::EVENT_AFTER_REFRESH_CACHE, $event);
        }
    }

    /**
     * Refreshes the entire cache.
     */
    public function refreshAll()
    {
        $event = $this->onBeforeRefresh();

        if (!$event->isValid) {
            return;
        }

        // Get cached site URIs before flushing the cache
        $siteUris = SiteUriHelper::getAllSiteUris();

        Blitz::$plugin->flushCache->flushAll();
        Blitz::$plugin->clearCache->clearAll();
        Blitz::$plugin->cachePurger->purgeAll();

        // Warm and deploy if enabled
        if (Blitz::$plugin->settings->cachingEnabled && Blitz::$plugin->settings->warmCacheAutomatically) {
            Blitz::$plugin->cacheWarmer->warmUris($siteUris, Blitz::$plugin->cachePurger->warmCacheDelay);

            Blitz::$plugin->deployer->deployUris($siteUris, Blitz::$plugin->cachePurger->warmCacheDelay);
        }

        // Fire an 'afterRefreshCache' event
        if ($this->hasEventHandlers(self::EVENT_AFTER_REFRESH_CACHE)) {
            $this->trigger(self::EVENT_AFTER_REFRESH_CACHE, $event);
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

        $this->_cacheIds = array_merge($this->_cacheIds, $cacheIds);

        // Check for expired elements to invalidate
        $elementExpiryDates = ElementExpiryDateRecord::find()
            ->where(['<', 'expiryDate', $now])
            ->all();

        if (!empty($elementExpiryDates)) {
            $elementsService = Craft::$app->getElements();

            foreach ($elementExpiryDates as $elementExpiryDate) {
                /** @var ElementExpiryDateRecord $elementExpiryDate */
                $element = $elementsService->getElementById($elementExpiryDate->elementId);

                // This should happen before invalidating the element so that other expiry dates will be saved
                $elementExpiryDate->delete();

                if ($element !== null) {
                    $this->addElement($element);
                }
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
        $siteUris = SiteUriHelper::getUrlSiteUris($urls);

        foreach ($siteUris as $siteUri) {
            // Check for cache record
            $cacheIds = CacheRecord::find()
                ->select('id')
                ->where([
                    'siteId' => $siteUri->siteId,
                    'uri' => $siteUri->uri,
                ])
                ->column();

            if (!empty($cacheIds)) {
                $this->_cacheIds = array_merge($this->_cacheIds, $cacheIds);
            }
        }

        $this->refresh(true);
    }

    /**
     * Refreshes tagged cache.
     *
     * @param string[] $tags
     */
    public function refreshTaggedCache(array $tags)
    {
        // Check for tagged cache IDs to invalidate
        $cacheIds = Blitz::$plugin->cacheTags->getCacheIds($tags);

        $this->_cacheIds = array_merge($this->_cacheIds, $cacheIds);

        $this->refresh(true);
    }

    // Protected Methods
    // =========================================================================

    /**
     * Fires an onBeforeRefresh event.
     *
     * @param array|null $config
     *
     * @return RefreshCacheEvent
     */
    protected function onBeforeRefresh(array $config = [])
    {
        $event = new RefreshCacheEvent($config);

        if ($this->hasEventHandlers(self::EVENT_BEFORE_REFRESH_CACHE)) {
            $this->trigger(self::EVENT_BEFORE_REFRESH_CACHE, $event);
        }

        return $event;
    }
}
