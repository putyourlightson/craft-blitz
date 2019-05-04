<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\services;

use Craft;
use craft\base\Component;
use craft\base\Element;
use craft\base\ElementInterface;
use craft\elements\GlobalSet;
use craft\helpers\Db;
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
     * Returns cache IDs for an array of elements.
     *
     * @param int[] $elementIds
     * @param int[] $ignoreCacheIds
     *
     * @return int[]
     */
    public function getElementCacheIds(array $elementIds, array $ignoreCacheIds = []): array
    {
        return ElementCacheRecord::find()
            ->select('cacheId')
            ->where(['elementId' => $elementIds])
            ->andWhere(['not', ['cacheId' => $ignoreCacheIds]])
            ->groupBy('cacheId')
            ->column();
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
        $this->_cacheIds = array_merge($this->_cacheIds,
            $this->getElementCacheIds([$element->getId()])
        );
    }

    /**
     * Adds an element to refresh.
     *
     * @param ElementInterface $element
     */
    public function addElement(ElementInterface $element)
    {
        // Return if not an Element
        if (!($element instanceof Element)) {
            return;
        }

        // Return if propagating
        if ($element->propagating) {
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
                Blitz::$plugin->warmCache->warmAll();
            }

            return;
        }

        /** @var Element $element */
        $elementType = get_class($element);

        // Don't proceed if this is a non cacheable element type
        if (in_array($elementType, ElementTypeHelper::getNonCacheableElementTypes(), true)) {
            return;
        }

        // Cast ID to integer to ensure the strict type check below works
        $elementId = (int)$element->getId();

        // Don't proceed if this entry has already been added
        if (in_array($elementId, $this->_elementIds, true)) {
            return;
        }

        $this->_elementIds[] = $elementId;

        if (!in_array($elementType, $this->_elementTypes, true)) {
            $this->_elementTypes[] = $elementType;
        }

        $this->addElementExpiryDates($element);

        // If batch mode is on then the refresh will be triggered later
        if ($this->batchMode === false) {
            $this->refresh();
        }
    }

    /**
     * Adds expiry dates for a given element.
     *
     * @param Element $element
     */
    public function addElementExpiryDates(Element $element)
    {
        $now = new DateTime();

        if (!empty($element->postDate) && $element->postDate > $now) {
            $expiryDate = $element->postDate;
        }
        else if (!empty($element->expiryDate) && $element->expiryDate > $now) {
            $expiryDate = $element->expiryDate;
        }

        if (empty($expiryDate)) {
            return;
        }

        $this->addElementExpiryDate($element, $expiryDate);
    }

    /**
     * Adds an expiry date for a given element.
     *
     * @param Element $element
     * @param DateTime $expiryDate
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
     * Refreshes the cache.
     *
     * @param bool $forceClear
     */
    public function refresh(bool $forceClear = false)
    {
        if (empty($this->_cacheIds) && empty($this->_elementIds)) {
            return;
        }

        Craft::$app->getQueue()->push(new RefreshCacheJob([
            'cacheIds' => $this->_cacheIds,
            'elementIds' => $this->_elementIds,
            'elementTypes' => $this->_elementTypes,
            'forceClear' => $forceClear,
        ]));
    }

    /**
     * Performs actions to finalise the refresh.
     *
     * @param SiteUriModel[] $siteUris
     */
    public function afterRefresh(array $siteUris)
    {
        Blitz::$plugin->cachePurger->purgeUris($siteUris);

        if (Blitz::$plugin->settings->cachingEnabled && Blitz::$plugin->settings->warmCacheAutomatically) {
            Blitz::$plugin->warmCache->warmUris($siteUris);
        }

        // Fire an 'afterRefreshCache' event
        if ($this->hasEventHandlers(self::EVENT_AFTER_REFRESH_CACHE)) {
            $this->trigger(self::EVENT_AFTER_REFRESH_CACHE, new RefreshCacheEvent([
                'siteUris' => $siteUris,
            ]));
        }
    }

    /**
     * Refreshes the entire cache.
     */
    public function refreshAll()
    {
        // Get cached site URIs before flushing the cache
        $siteUris = SiteUriHelper::getAllSiteUris();

        Blitz::$plugin->clearCache->clearAll();
        Blitz::$plugin->flushCache->flushAll();
        Blitz::$plugin->cachePurger->purgeAll();
        Blitz::$plugin->warmCache->warmUris($siteUris);
    }

    /**
     * Refreshes expired cache.
     */
    public function refreshExpiredCache()
    {
        $this->batchMode = true;

        // Check for expired caches to invalidate
        $cacheIds = CacheRecord::find()
            ->select('id')
            ->where(['<', 'expiryDate', Db::prepareDateForDb(new DateTime())])
            ->column();

        $this->_cacheIds = array_merge($this->_cacheIds, $cacheIds);

        // Check for expired elements to invalidate
        $elementExpiryDates = ElementExpiryDateRecord::find()
            ->where(['<', 'expiryDate', Db::prepareDateForDb(new DateTime())])
            ->all();

        if (!empty($elementExpiryDates)) {
            $elementsService = Craft::$app->getElements();

            /** @var ElementExpiryDateRecord $elementExpiryDate */
            foreach ($elementExpiryDates as $elementExpiryDate) {
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
     * Refreshes tagged cache.
     *
     * @param string[] $tags
     */
    public function refreshTaggedCache(array $tags)
    {
        $this->batchMode = true;

        // Check for tagged cache IDs to invalidate
        $cacheIds = Blitz::$plugin->cacheTags->getCacheIds($tags);

        $this->_cacheIds = array_merge($this->_cacheIds, $cacheIds);

        $this->refresh(true);
    }
}
