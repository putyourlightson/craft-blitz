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
use craft\helpers\App;
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
use yii\db\ActiveQuery;

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
     * @var int[]
     */
    private $_invalidateCacheIds = [];

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
            foreach ($this->_settings->excludedUriPatterns as $excludedUriPattern) {
                if ($this->_matchUriPattern($excludedUriPattern, $siteId, $uri)) {
                    return false;
                }
            }
        }

        if (is_array($this->_settings->includedUriPatterns)) {
            foreach ($this->_settings->includedUriPatterns as $includedUriPattern) {
                if ($this->_matchUriPattern($includedUriPattern, $siteId, $uri)) {
                    return true;
                }
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

        /** @var Element $element */
        if (!in_array($element->id, $this->_addElementCaches, true)) {
            $this->_addElementCaches[] = $element->id;
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

        // Don't proceed if the query has a value set for ID
        if ($elementQuery->id) {
            return;
        }

        // Based on code from includeElementQueryInTemplateCaches method in \craft\services\TemplateCaches)
        $query = $elementQuery->query;
        $subQuery = $elementQuery->subQuery;
        $customFields = $elementQuery->customFields;

        // Nullify values
        $elementQuery->query = null;
        $elementQuery->subQuery = null;
        $elementQuery->customFields = null;

        // Base64-encode the query so db\Connection::quoteSql() doesn't tweak any of the table/columns names
        $encodedQuery = base64_encode(serialize($elementQuery));

        // Hash the encoded query for quicker indexing
        $hash = md5($encodedQuery);

        // Set back to original values
        $elementQuery->query = $query;
        $elementQuery->subQuery = $subQuery;
        $elementQuery->customFields = $customFields;

        // Use DB connection so we can batch insert and exclude audit columns
        $db = Craft::$app->getDb();

        // Get element query record from type and hash or create one if it does not exist
        $values = [
            'type' => $elementQuery->elementType,
            'hash' => $hash,
        ];

        $queryId = ElementQueryRecord::find()
            ->select('id')
            ->where($values)
            ->scalar();

        if (!$queryId) {
            // Set query before inserting
            $values['query'] = $encodedQuery;

            $db->createCommand()
                ->insert(ElementQueryRecord::tableName(), $values, false)
                ->execute();

            $queryId = $db->getLastInsertID();
        }

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
        // Get the URI path
        $uriPath = preg_replace('/\?.*/', '', $uri);

        // If the URI path represents an element then add the full URI to the element cache
        $element = Craft::$app->getElements()->getElementByUri(trim($uriPath, '/'), $siteId, true);

        if ($element !== null) {
            $this->addElementCache($element);
        }

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
            ->batchInsert(ElementCacheRecord::tableName(), ['cacheId', 'elementId'], $values, false)
            ->execute();

        // Add element query caches to database
        $values = [];

        foreach ($this->_addElementQueryCaches as $queryId) {
            $values[] = [$cacheId, $queryId];
        }

        $db->createCommand()
            ->batchInsert(ElementQueryCacheRecord::tableName(), ['cacheId', 'queryId'], $values, false)
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

            if ($this->_settings->cachingEnabled AND $this->_settings->warmCacheAutomatically) {
                Craft::$app->getQueue()->push(new WarmCacheJob(['urls' => $this->getAllCacheUrls()]));
            }

            return;
        }

        // Don't proceed if this is a non cacheable element type
        if (in_array(get_class($element), $this->getNonCacheableElementTypes(), true)) {
            return;
        }

        // Delete the cached file immediately if this element has a URI
        /** @var Element $element */
        if ($element->uri !== null) {
            Blitz::$plugin->file->deleteFileByUri($element->siteId, $element->uri);
        }

        App::maxPowerCaptain();

        // Get element cache records grouped by cache ID
        $elementCacheRecords = ElementCacheRecord::find()
            ->select('cacheId')
            ->where(['elementId' => $element->id])
            ->groupBy('cacheId')
            ->all();

        /** @var ElementCacheRecord[] $elementCacheRecords */
        foreach ($elementCacheRecords as $elementCacheRecord) {
            if (!in_array($elementCacheRecord->cacheId, $this->_invalidateCacheIds, true)) {
                $this->_invalidateCacheIds[] = $elementCacheRecord->cacheId;
            }
        }

        // Get element query records of the element type without already saved cache IDs and without eager-loading
        $elementQueryRecords = ElementQueryRecord::find()
            ->select(['id', 'query'])
            ->innerJoinWith([
                'elementQueryCaches' => function(ActiveQuery $query) {
                    $query->where(['not', ['cacheId' => $this->_invalidateCacheIds]]);
                }
            ], false)
            ->where(['type' => Craft::$app->getElements()->getElementTypeById($element->id)])
            ->all();

        /** @var ElementQueryRecord[] $elementQueryRecords */
        foreach ($elementQueryRecords as $elementQueryRecord) {
            /** @var ElementQuery|false $query */
            /** @noinspection UnserializeExploitsInspection */
            $query = @unserialize(base64_decode($elementQueryRecord->query));

            // If the element ID is in the query's results
            if ($query !== false && in_array($element->id, $query->ids(), true)) {
                // Get related element query cache records
                $elementQueryCacheRecords = $elementQueryRecord->elementQueryCaches;

                // Add cache IDs to the array that do not already exist
                foreach ($elementQueryCacheRecords as $elementQueryCacheRecord) {
                    if (!in_array($elementQueryCacheRecord->cacheId, $this->_invalidateCacheIds, true)) {
                        $this->_invalidateCacheIds[] = $elementQueryCacheRecord->cacheId;
                    }
                }
            }
        }
    }

    /**
     * Invalidates the cache.
     */
    public function invalidateCache()
    {
        if (empty($this->_invalidateCacheIds)) {
            return;
        }

        Craft::$app->getQueue()->push(new RefreshCacheJob([
            'cacheIds' => $this->_invalidateCacheIds,
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
        $elementQueryRecords = ElementQueryRecord::find()
            ->joinWith('elementQueryCaches')
            ->where(['cacheId' => null])
            ->all();

        foreach ($elementQueryRecords as $elementQueryRecord) {
            $elementQueryRecord->delete();
        }
    }

    /**
     * Empties the entire cache.
     *
     * @param bool $clearRecords
     */
    public function emptyCache(bool $clearRecords = false)
    {
        // Empties the file cache
        Blitz::$plugin->file->emptyFileCache();

        // Get all cache IDs
        $cacheIds = CacheRecord::find()
            ->select('id')
            ->column();

        $this->afterRefreshCache($cacheIds);

        if ($clearRecords) {
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
        if (empty($cacheIds)) {
            return;
        }

        // Fire an 'afterRefreshCache' event
        if ($this->hasEventHandlers(self::EVENT_AFTER_REFRESH_CACHE)) {
            $this->trigger(self::EVENT_AFTER_REFRESH_CACHE, new RefreshCacheEvent([
                'cacheIds' => $cacheIds,
            ]));
        }
    }

    /**
     * Gets all cache URLs.
     *
     * @return string[]
     */
    public function getAllCacheUrls(): array
    {
        $urls = [];

        // Get URLs from all cache records
        $cacheRecords = CacheRecord::find()
            ->select(['siteId', 'uri'])
            ->all();

        /** @var CacheRecord $cacheRecord */
        foreach ($cacheRecords as $cacheRecord) {
            if ($this->getIsCacheableUri($cacheRecord->siteId, $cacheRecord->uri)) {
                $urls[] = UrlHelper::siteUrl($cacheRecord->uri, null, null, $cacheRecord->siteId);
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

    // Private Methods
    // =========================================================================

    /**
     * Matches a URI pattern.
     *
     * @param array $pattern
     * @param int $siteId
     * @param string $uri
     *
     * @return bool
     */
    private function _matchUriPattern(array $pattern, int $siteId, string $uri): bool
    {
        // Return false if site is not empty and does not match the provided site ID
        if (!empty($pattern[1]) && $pattern[1] != $siteId) {
            return false;
        }

        $uriPattern = $pattern[0];

        if ($uriPattern == '') {
            return false;
        }

        // Replace "*" with 0 or more characters as otherwise it'll throw an error
        if ($uriPattern == '*') {
            $uriPattern = '.*';
        }

        // Escape hash symbols
        $uriPattern = str_replace('#', '\#', $uriPattern);

        return preg_match('#'.trim($uriPattern, '/').'#', trim($uri, '/'));
    }
}
