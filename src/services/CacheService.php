<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\services;

use Craft;
use craft\base\Component;
use craft\base\Element;
use craft\base\ElementInterface;
use craft\elements\db\ElementQuery;
use craft\elements\GlobalSet;
use craft\elements\MatrixBlock;
use craft\helpers\UrlHelper;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\events\RefreshCacheEvent;
use putyourlightson\blitz\events\RegisterNonCacheableElementTypesEvent;
use putyourlightson\blitz\jobs\RefreshCacheJob;
use putyourlightson\blitz\jobs\WarmCacheJob;
use putyourlightson\blitz\models\SettingsModel;
use putyourlightson\blitz\records\CacheRecord;
use putyourlightson\blitz\records\ElementCacheRecord;
use putyourlightson\blitz\records\ElementQueryCacheRecord;
use putyourlightson\blitz\records\ElementQueryRecord;

/**
 * @property bool $isCacheableRequest
 */
class CacheService extends Component
{
    // Constants
    // =========================================================================

    /**
     * @event RegisterNonCacheableElementTypesEvent
     */
    const EVENT_REGISTER_NON_CACHEABLE_ELEMENT_TYPES = 'registerNonCacheableElementTypes';

    /**
     * @event RefreshCacheEvent
     */
    const EVENT_AFTER_REFRESH_CACHE = 'afterRefreshCache';

    // Properties
    // =========================================================================

    /**
     * @var SettingsModel
     */
    private $_settings;

    /**
     * @var string[]|null
     */
    private $_nonCacheableElementTypes;

    /**
     * @var int[]
     */
    private $_addElementCaches = [];

    /**
     * @var int[]
     */
    private $_addElementQueryCaches = [];

    /**
     * @var array
     */
    private $_defaultElementQueryParams = [];

    /**
     * @var int[]
     */
    private $_invalidateCacheIds = [];
    /**
     * @var int[]
     */
    private $_invalidateElementIds = [];

    /**
     * @var string[]
     */
    private $_invalidateElementTypes = [];

    // Public Methods
    // =========================================================================

    public function init()
    {
        parent::init();

        $this->_settings = Blitz::$plugin->getSettings();
    }

    /**
     * Returns whether the request is cacheable.
     *
     * @return bool
     */
    public function getIsCacheableRequest(): bool
    {
        $request = Craft::$app->getRequest();
        $response = Craft::$app->getResponse();

        // Ensure this is a front-end get that is not a console request or an action request or live preview and returns status 200
        if (!$request->getIsSiteRequest() || !$request->getIsGet() || $request->getIsActionRequest() || $request->getIsLivePreview() || !$response->getIsOk()) {
            return false;
        }

        $user = Craft::$app->getUser()->getIdentity();

        // Ensure that if user is logged in then debug toolbar is not enabled
        if ($user !== null && $user->getPreference('enableDebugToolbarForSite')) {
            return false;
        }

        if (!$this->_settings->cachingEnabled) {
            return false;
        }

        if ($this->_settings->queryStringCaching == 0 && $request->getQueryStringWithoutPath() !== '') {
            return false;
        }

        return true;
    }

    /**
     * Returns whether the URI is cacheable.
     *
     * @param int $siteId
     * @param string $uri
     *
     * @return bool
     */
    public function getIsCacheableUri(int $siteId, string $uri): bool
    {
        // Ignore URIs that contain index.php
        if (strpos($uri, 'index.php') !== false) {
            return false;
        }

        // Excluded URI patterns take priority
        if (is_array($this->_settings->excludedUriPatterns)) {
            if ($this->matchesUriPattern($this->_settings->excludedUriPatterns, $siteId, $uri)) {
                return false;
            }
        }

        if (is_array($this->_settings->includedUriPatterns)) {
            if ($this->matchesUriPattern($this->_settings->includedUriPatterns, $siteId, $uri)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns non cacheable element types.
     *
     * @return string[]
     */
    public function getNonCacheableElementTypes(): array
    {
        if ($this->_nonCacheableElementTypes !== null) {
            return $this->_nonCacheableElementTypes;
        }

        $elementTypes = [
            GlobalSet::class,
            MatrixBlock::class,
        ];

        $event = new RegisterNonCacheableElementTypesEvent([
            'elementTypes' => $elementTypes,
        ]);
        $this->trigger(self::EVENT_REGISTER_NON_CACHEABLE_ELEMENT_TYPES, $event);

        $this->_nonCacheableElementTypes = $event->elementTypes;

        return $this->_nonCacheableElementTypes;
    }

    /**
     * Adds an element cache.
     *
     * @param ElementInterface $element
     */
    public function addElementCache(ElementInterface $element)
    {
        // Don't proceed if element caching is disabled
        if (!$this->_settings->cacheElements) {
            return;
        }

        // Don't proceed if this is a non cacheable element type
        if (in_array(get_class($element), $this->getNonCacheableElementTypes(), true)) {
            return;
        }

        // Cast ID to integer to ensure the strict type check below works
        /** @var Element $element */
        $elementId = (int)$element->id;

        if (!in_array($elementId, $this->_addElementCaches, true)) {
            $this->_addElementCaches[] = $elementId;
        }
    }

    /**
     * Adds an element query cache.
     *
     * @param ElementQuery $elementQuery
     */
    public function addElementQueryCache(ElementQuery $elementQuery)
    {
        // Don't proceed if element query caching is disabled
        if (!$this->_settings->cacheElementQueries) {
            return;
        }

        // Don't proceed if this is a non cacheable element type
        if (in_array($elementQuery->elementType, $this->getNonCacheableElementTypes(), true)) {
            return;
        }

        // Don't proceed if the query has a value set for ID or an `elements.id` value set for where (used when eager loading elements)
        if ($elementQuery->id || !empty($elementQuery->where['elements.id'])) {
            return;
        }

        $params = json_encode($this->_getUniqueElementQueryParams($elementQuery));

        // Create a unique index from the element type and parameters for quicker indexing and less storage
        $index = sprintf('%u', crc32($elementQuery->elementType.$params));

        // Use DB connection so we can insert and exclude audit columns
        $db = Craft::$app->getDb();

        // Get element query record from index or create one if it does not exist
        $queryId = ElementQueryRecord::find()
            ->select('id')
            ->where(['index' => $index])
            ->scalar();

        if (!$queryId) {
            $db->createCommand()
                ->insert(ElementQueryRecord::tableName(), [
                    'index' => $index,
                    'type' => $elementQuery->elementType,
                    'params' => $params,
                ], false)
                ->execute();

            $queryId = $db->getLastInsertID();
        }

        // Cast ID to integer to ensure the strict type check below works
        $queryId = (int)$queryId;

        if (!in_array($queryId, $this->_addElementQueryCaches, true)) {
            $this->_addElementQueryCaches[] = $queryId;
        }
    }

    /**
     * Caches the output to a URI.
     *
     * @param string $output
     * @param int $siteId
     * @param string $uri
     */
    public function cacheOutput(string $output, int $siteId, string $uri)
    {
        // Use DB connection so we can batch insert and exclude audit columns
        $db = Craft::$app->getDb();

        // Get cache record or create one if it does not exist
        $values = [
            'siteId' => $siteId,
            'uri' => $uri,
        ];

        $cacheId = CacheRecord::find()
            ->select('id')
            ->where($values)
            ->scalar();

        if (!$cacheId) {
            $db->createCommand()
                ->insert(CacheRecord::tableName(), $values, false)
                ->execute();

            $cacheId = $db->getLastInsertID();
        }

        // Add element caches to database
        $values = [];

        foreach ($this->_addElementCaches as $elementId) {
            $values[] = [$cacheId, $elementId];
        }

        $db->createCommand()
            ->batchInsert(
                ElementCacheRecord::tableName(),
                ['cacheId', 'elementId'],
                $values,
                false)
            ->execute();

        // Add element query caches to database
        $values = [];

        foreach ($this->_addElementQueryCaches as $queryId) {
            $values[] = [$cacheId, $queryId];
        }

        $db->createCommand()
            ->batchInsert(
                ElementQueryCacheRecord::tableName(),
                ['cacheId', 'queryId'],
                $values,
                false)
            ->execute();

        Blitz::$plugin->file->cacheToFile($output, $siteId, $uri);
    }

    /**
     * Invalidates the cache by an element.
     *
     * @param ElementInterface $element
     */
    public function invalidateElement(ElementInterface $element)
    {
        // Clear and the cache if this is a global set element as they are populated on every request
        if ($element instanceof GlobalSet) {
            $this->emptyCache();

            if ($this->_settings->cachingEnabled && $this->_settings->warmCacheAutomatically && $this->_settings->warmCacheAutomaticallyForGlobals) {
                Craft::$app->getQueue()->push(new WarmCacheJob([
                    'urls' => $this->getAllCacheableUrls()
                ]));
            }

            return;
        }

        /** @var Element $element */
        $elementType = get_class($element);

        // Don't proceed if this is a non cacheable element type
        if (in_array($elementType, $this->getNonCacheableElementTypes(), true)) {
            return;
        }

        // Cast ID to integer to ensure the strict type check below works
        $elementId = (int)$element->id;

        // Don't proceed if this entry has already been added
        if (in_array($elementId, $this->_invalidateElementIds, true)) {
            return;
        }

        $this->_invalidateElementIds[] = $elementId;

        if (!in_array($elementType, $this->_invalidateElementTypes, true)) {
            $this->_invalidateElementTypes[] = $elementType;
        }

        // Get the element cache IDs to clear now as we may not be able to detect it later in a job (if the element was deleted)
        $elementCacheRecords = ElementCacheRecord::find()
            ->select('cacheId')
            ->where(['elementId' => $elementId])
            ->groupBy('cacheId')
            ->all();

        /** @var ElementCacheRecord[] $elementCacheRecords */
        foreach ($elementCacheRecords as $elementCacheRecord) {
            if (!in_array($elementCacheRecord->cacheId, $this->_invalidateCacheIds, true)) {
                $this->_invalidateCacheIds[] = $elementCacheRecord->cacheId;
            }
        }
    }

    /**
     * Invalidates the cache.
     */
    public function invalidateCache()
    {
        if (empty($this->_invalidateCacheIds) && empty($this->_invalidateElementIds)) {
            return;
        }

        Craft::$app->getQueue()->push(new RefreshCacheJob([
            'cacheIds' => $this->_invalidateCacheIds,
            'elementIds' => $this->_invalidateElementIds,
            'elementTypes' => $this->_invalidateElementTypes,
        ]));
    }

    /**
     * Clears cache records for a given site and URI.
     *
     * @param int $siteId
     * @param string $uri
     */
    public function clearCacheRecords(int $siteId, string $uri)
    {
        CacheRecord::deleteAll([
            'siteId' => $siteId,
            'uri' => $uri,
        ]);
    }

    /**
     * Cleans element query table.
     */
    public function cleanElementQueryTable()
    {
        // Get and delete element query records without an associated element query cache
        $elementQueryRecordIds = ElementQueryRecord::find()
            ->select('id')
            ->joinWith('elementQueryCaches')
            ->where(['cacheId' => null])
            ->column();

        ElementQueryRecord::deleteAll(['id' => $elementQueryRecordIds]);
    }

    /**
     * Empties the entire cache.
     *
     * @param bool $flush
     */
    public function emptyCache(bool $flush = false)
    {
        // Empties the file cache
        Blitz::$plugin->file->emptyFileCache();

        // Get all cache IDs
        $cacheIds = CacheRecord::find()
            ->select('id')
            ->column();

        // Trigger afterRefreshCache event
        $this->afterRefreshCache($cacheIds);

        if ($flush) {
            // Delete all cache records
            CacheRecord::deleteAll();
        }
    }

    /**
     * Fires an event after the cache is refreshed.
     *
     * @param int[] $cacheIds
     */
    public function afterRefreshCache(array $cacheIds)
    {
        // Fire an 'afterRefreshCache' event
        if ($this->hasEventHandlers(self::EVENT_AFTER_REFRESH_CACHE)) {
            $this->trigger(self::EVENT_AFTER_REFRESH_CACHE, new RefreshCacheEvent([
                'cacheIds' => $cacheIds,
            ]));
        }
    }

    /**
     * Gets a URLs given a site and URI.
     *
     * @param int $siteId
     * @param string $uri
     *
     * @return string
     */
    public function getSiteUrl(int $siteId, string $uri): string
    {
        return UrlHelper::siteUrl($uri, null, null, $siteId);
    }

    /**
     * Gets cache URLs given an array of cache IDs.
     *
     * @param int[] $cacheIds
     *
     * @return string[]
     */
    public function getCacheUrls(array $cacheIds): array
    {
        $urls = [];

        /** @var CacheRecord[] $cacheRecords */
        $cacheRecords = CacheRecord::find()
            ->select('uri, siteId')
            ->where(['id' => $cacheIds])
            ->all();

        foreach ($cacheRecords as $cacheRecord) {
            $urls[] = $this->getSiteUrl($cacheRecord->siteId, $cacheRecord->uri);
        }

        return $urls;
    }

    /**
     * Gets all cacheable URLs.
     *
     * @return string[]
     */
    public function getAllCacheableUrls(): array
    {
        $urls = [];

        // Get URLs from all cache records
        $cacheRecords = CacheRecord::find()
            ->select(['siteId', 'uri'])
            ->all();

        /** @var CacheRecord $cacheRecord */
        foreach ($cacheRecords as $cacheRecord) {
            if ($this->getIsCacheableUri($cacheRecord->siteId, $cacheRecord->uri)) {
                $urls[] = $this->getSiteUrl($cacheRecord->siteId, $cacheRecord->uri);
            }
        }

        // Get URLs from all element types
        $elementTypes = Craft::$app->getElements()->getAllElementTypes();

        /** @var Element $elementType */
        foreach ($elementTypes as $elementType) {
            if ($elementType::hasUris()) {
                // Loop through all sites to ensure we warm all site element URLs
                $sites = Craft::$app->getSites()->getAllSites();

                foreach ($sites as $site) {
                    $elements = $elementType::find()->siteId($site->id)->all();

                    /** @var Element $element */
                    foreach ($elements as $element) {
                        $uri = trim($element->uri, '/');
                        $uri = ($uri == '__home__' ? '' : $uri);

                        if ($uri !== null && $this->getIsCacheableUri($site->id, $uri)) {
                            $url = $element->getUrl();

                            if ($url !== null && !in_array($url, $urls, true)) {
                                $urls[] = $url;
                            }
                        }
                    }
                }
            }
        }

        return $urls;
    }

    /**
     * Matches a URI pattern in a set of patterns.
     *
     * @param array $patterns
     * @param int $siteId
     * @param string $uri
     *
     * @return bool
     */
    public function matchesUriPattern(array $patterns, int $siteId, string $uri): bool
    {
        foreach ($patterns as $pattern) {
            // Don't proceed if site is not empty and does not match the provided site ID
            if (!empty($pattern[1]) && $pattern[1] != $siteId) {
                continue;
            }

            $uriPattern = $pattern[0];

            // Replace a blank string with the homepage
            if ($uriPattern == '') {
                $uriPattern = '^$';
            }

            // Replace "*" with 0 or more characters as otherwise it'll throw an error
            if ($uriPattern == '*') {
                $uriPattern = '.*';
            }

            // Trim slashes
            $uriPattern = trim($uriPattern, '/');

            // Escape hash symbols
            $uriPattern = str_replace('#', '\#', $uriPattern);

            if (preg_match('#'.$uriPattern.'#', trim($uri, '/'))) {
                return true;
            }
        }

        return false;
    }

    // Private Methods
    // =========================================================================

    /**
     * Returns an element query's default parameters for a given element type.
     *
     * @param string $elementType
     *
     * @return array
     */
    private function _getDefaultElementQueryParams(string $elementType): array
    {
        if (!empty($this->_defaultElementQueryParams[$elementType])) {
            return $this->_defaultElementQueryParams[$elementType];
        }

        $this->_defaultElementQueryParams[$elementType] = get_object_vars($elementType::find());

        $ignoreParams = ['select', 'with', 'query', 'subQuery', 'customFields'];

        foreach ($ignoreParams as $key) {
            unset($this->_defaultElementQueryParams[$elementType][$key]);
        }

        return $this->_defaultElementQueryParams[$elementType];
    }

    /**
     * Returns the element query's unique parameters.
     *
     * @param ElementQuery $elementQuery
     *
     * @return array
     */
    private function _getUniqueElementQueryParams(ElementQuery $elementQuery): array
    {
        $params = [];

        $defaultParams = $this->_getDefaultElementQueryParams($elementQuery->elementType);

        foreach ($defaultParams as $key => $default) {
            $value = $elementQuery->{$key};

            if ($value !== $default)
                $params[$key] = $value;

                // Convert datetime parameters to Unix timestamps
                if ($value instanceof \DateTime) {
                    $params[$key] = $value->getTimestamp();
                }

                // Convert element parameters to ID
                if ($value instanceof Element) {
                    $params[$key] = $value->id;
                }
        }

        return $params;
    }
}
