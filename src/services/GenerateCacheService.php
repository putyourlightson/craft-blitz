<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\services;

use Craft;
use craft\base\Component;
use craft\base\ElementInterface;
use craft\elements\db\ElementQuery;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\ArrayHelper;
use craft\helpers\Db;
use craft\helpers\StringHelper;
use DateTime;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\helpers\ElementTypeHelper;
use putyourlightson\blitz\models\CacheOptionsModel;
use putyourlightson\blitz\models\SiteUriModel;
use putyourlightson\blitz\records\CacheRecord;
use putyourlightson\blitz\records\ElementCacheRecord;
use putyourlightson\blitz\records\ElementQueryCacheRecord;
use putyourlightson\blitz\records\ElementQueryRecord;
use putyourlightson\blitz\records\ElementQuerySourceRecord;
use yii\db\Exception;
use yii\log\Logger;

class GenerateCacheService extends Component
{
    // Constants
    // =========================================================================

    /**
     * @const string
     */
    const MUTEX_LOCK_NAME_ELEMENT_QUERY_RECORDS = 'blitz:elementQueryRecords';

    /**
     * @const string
     */
    const MUTEX_LOCK_NAME_CACHE_RECORDS = 'blitz:cacheRecords';

    // Properties
    // =========================================================================

    /**
     * @var CacheOptionsModel
     */
    public $options;

    /**
     * @var int[]
     */
    public $elementCaches = [];

    /**
     * @var int[]
     */
    public $elementQueryCaches = [];

    /**
     * @var array
     */
    private $_defaultElementQueryParams = [];

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();

        $this->options = new CacheOptionsModel();

        // Set default attributes from the plugin settings
        $this->options->setAttributes(Blitz::$plugin->settings->getAttributes(), false);
    }

    /**
     * Adds an element to be cached.
     *
     * @param ElementInterface $element
     */
    public function addElement(ElementInterface $element)
    {
        // Don't proceed if element caching is disabled
        if (!Blitz::$plugin->settings->cacheElements || !$this->options->cacheElements) {
            return;
        }

        // Don't proceed if not a cacheable element type
        if (!ElementTypeHelper::getIsCacheableElementType(get_class($element))) {
            return;
        }

        if (!in_array($element->getId(), $this->elementCaches)) {
            $this->elementCaches[] = $element->getId();
        }
    }

    /**
     * Adds an element query to be cached.
     *
     * @param ElementQuery $elementQuery
     */
    public function addElementQuery(ElementQuery $elementQuery)
    {
        // Don't proceed if element query caching is disabled
        if (!Blitz::$plugin->settings->cacheElementQueries
            || !$this->options->cacheElementQueries
        ) {
            return;
        }

        // Don't proceed if not a cacheable element type
        if (empty($elementQuery->elementType)
            || !ElementTypeHelper::getIsCacheableElementType($elementQuery->elementType)
        ) {
            return;
        }

        // Don't proceed if the query has fixed IDs
        if ($this->_hasFixedIds($elementQuery)) {
            return;
        }

        // Don't proceed if the query has a join
        if (!empty($elementQuery->join)) {
            return;
        }

        $this->saveElementQuery($elementQuery);
    }

    /**
     * Saves an element query.
     *
     * @param ElementQuery $elementQuery
     */
    public function saveElementQuery(ElementQuery $elementQuery)
    {
        $params = json_encode($this->_getUniqueElementQueryParams($elementQuery));

        // Create a unique index from the element type and parameters for quicker indexing and less storage
        $index = sprintf('%u', crc32($elementQuery->elementType.$params));

        $mutex = Craft::$app->getMutex();
        $lockName = self::MUTEX_LOCK_NAME_ELEMENT_QUERY_RECORDS.':'.$index;

        if (!$mutex->acquire($lockName, Blitz::$plugin->settings->mutexTimeout)) {
            return;
        }

        // Use DB connection so we can exclude audit columns when inserting
        $db = Craft::$app->getDb();

        // Get element query record from index or create one if it does not exist
        $queryId = ElementQueryRecord::find()
            ->select('id')
            ->where(['index' => $index])
            ->scalar();

        if (!$queryId) {
            try {
                $db->createCommand()
                    ->insert(ElementQueryRecord::tableName(), [
                        'index' => $index,
                        'type' => $elementQuery->elementType,
                        'params' => $params,
                    ], false)
                    ->execute();

                $queryId = $db->getLastInsertID();

                $this->saveElementQuerySources($elementQuery, $queryId);
            }
            catch (Exception $e) {
                Blitz::$plugin->log($e->getMessage(), [], 'error');
            }
        }

        if ($queryId && !in_array($queryId, $this->elementQueryCaches)) {
            $this->elementQueryCaches[] = $queryId;
        }

        $mutex->release($lockName);
    }

    /**
     * Saves an element query's sources.
     *
     * @param ElementQuery $elementQuery
     * @param string $queryId
     *
     * @throws Exception
     */
    public function saveElementQuerySources(ElementQuery $elementQuery, string $queryId)
    {
        $db = Craft::$app->getDb();

        $sourceIdAttribute = ElementTypeHelper::getSourceIdAttribute($elementQuery->elementType);
        $sourceIds = $sourceIdAttribute ? $elementQuery->$sourceIdAttribute : null;

        // Normalize source IDs
        $sourceIds = $this->_getNormalizedElementQueryIdParam($sourceIds);

        // Convert to an array
        if (!is_array($sourceIds)) {
            $sourceIds = [$sourceIds];
        }

        foreach ($sourceIds as $sourceId) {
            // Stop if a string is encountered
            if (is_string($sourceId)) {
                break;
            }

            $db->createCommand()
                ->insert(ElementQuerySourceRecord::tableName(), [
                    'sourceId' => $sourceId,
                    'queryId' => $queryId,
                ], false)
                ->execute();
        }
    }

    /**
     * Saves the cache and output for a site URI.
     *
     * @param string $output
     * @param SiteUriModel $siteUri
     */
    public function save(string $output, SiteUriModel $siteUri)
    {
        if (!$this->options->cachingEnabled) {
            return;
        }

        // Don't cache if the output contains any transform generation URLs
        // https://github.com/putyourlightson/craft-blitz/issues/125
        if (StringHelper::contains(stripslashes($output), 'assets/generate-transform')) {
            Blitz::$plugin->debug('Page not cached because it contains transform generation URLs.');

            return;
        }

        $mutex = Craft::$app->getMutex();
        $lockName = self::MUTEX_LOCK_NAME_CACHE_RECORDS;

        if (!$mutex->acquire($lockName, Blitz::$plugin->settings->mutexTimeout)) {
            Blitz::$plugin->debug('Page not cached because a `'.$lockName.'` mutex could not be acquired.');

            return;
        }

        // Use DB connection so we can batch insert and exclude audit columns
        $db = Craft::$app->getDb();

        $values = $siteUri->toArray();

        // Delete cache records so we get a fresh cache
        CacheRecord::deleteAll($values);

        if (!empty($this->options->expiryDate)) {
            $values = array_merge($values, [
                'expiryDate' => Db::prepareDateForDb($this->options->expiryDate),
            ]);
        }

        $db->createCommand()
            ->insert(CacheRecord::tableName(), $values, false)
            ->execute();

        $cacheId = (int)$db->getLastInsertID();

        // Add element caches to database
        $values = [];

        foreach ($this->elementCaches as $elementId) {
            $values[] = [$cacheId, $elementId];
        }

        $db->createCommand()
            ->batchInsert(ElementCacheRecord::tableName(),
                ['cacheId', 'elementId'],
                $values,
                false)
            ->execute();

        // Add element query caches to database
        $values = [];

        foreach ($this->elementQueryCaches as $queryId) {
            $values[] = [$cacheId, $queryId];
        }

        $db->createCommand()
            ->batchInsert(ElementQueryCacheRecord::tableName(),
                ['cacheId', 'queryId'],
                $values,
                false)
            ->execute();

        // Add tag caches to database
        if (!empty($this->options->tags)) {
            Blitz::$plugin->cacheTags->saveTags($this->options->tags, $cacheId);
        }

        if (Blitz::$plugin->settings->outputComments) {
            // Append timestamp
            $output .= '<!-- Cached by Blitz on '.date('c').' -->';
        }

        Blitz::$plugin->cacheStorage->save($output, $siteUri);

        $mutex->release($lockName);
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

            if ($value !== $default) {
                $params[$key] = $value;
            }
        }

        // Ignore specific empty params as they are redundant
        $ignoreEmptyParams = ['structureId', 'orderBy'];

        foreach ($ignoreEmptyParams as $key) {
            // Use `array_key_exists` rather than `isset` as it will return `true` for null results
            if (array_key_exists($key, $params) && empty($params[$key])) {
                unset($params[$key]);
            }
        }

        // Convert ID parameters to arrays
        foreach ($params as $key => $value) {
            if ($key == 'id' || substr($key, -2) == 'Id') {
                $params[$key] = $this->_getNormalizedElementQueryIdParam($value);
            }
        }

        // Convert the query parameter values recursively
        array_walk_recursive($params, [$this, '_convertQueryParamsRecursively']);

        return $params;
    }

    /**
     * Returns a normalized element query ID parameter.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    private function _getNormalizedElementQueryIdParam($value)
    {
        if ($value === null || is_int($value)) {
            return $value;
        }

        /**
         * Copied from Db helper
         * @see \craft\helpers\Db::_toArray
         */
        if (is_string($value)) {
            // Split it on the non-escaped commas
            $value = preg_split('/(?<!\\\),/', $value);

            // Remove any of the backslashes used to escape the commas
            foreach ($value as $key => $val) {
                // Remove leading/trailing whitespace
                $val = trim($val);

                // Remove any backslashes used to escape commas
                $val = str_replace('\,', ',', $val);

                $value[$key] = $val;
            }

            // Remove any empty elements and reset the keys
            $value = array_values(ArrayHelper::filterEmptyStringsFromArray($value));
        }

        if (is_array($value)) {
            // Convert numeric strings to integers
            foreach ($value as $key => $val) {
                if (is_string($val) && is_numeric($val)) {
                    $value[$key] = (int)$val;
                }
            }

            // If there is only a single value in the array then set the value to it
            if (count($value) === 1) {
                $value = reset($value);
            }
        }

        return $value;
    }

    /**
     * Returns whether the element query has fixed IDs.
     *
     * @param ElementQuery $elementQuery
     *
     * @return bool
     */
    private function _hasFixedIds(ElementQuery $elementQuery): bool
    {
        // The query values to check
        $values = [
            $elementQuery->id,
            $elementQuery->uid,
            $elementQuery->where['elements.id'] ?? null,
            $elementQuery->where['elements.uid'] ?? null,
        ];

        foreach ($values as $value) {
            if (empty($value)) {
                continue;
            }

            if (is_array($value)) {
                $value = $value[0] ?? null;
            }

            if (is_numeric($value)) {
                return true;
            }

            if (is_string($value) && stripos($value, 'not') !== 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Converts query parameter values to more concise formats recursively.
     *
     * @param mixed $value
     */
    private function _convertQueryParamsRecursively(&$value)
    {
        // Convert elements to their ID
        if ($value instanceof ElementInterface) {
            $value = $value->getId();
            return;
        }

        // Convert element queries to element IDs
        if ($value instanceof ElementQueryInterface) {
            $value = $value->ids();
            return;
        }

        // Convert DateTime objects to Unix timestamp
        if ($value instanceof DateTime) {
            $value = $value->getTimestamp();
            return;
        }
    }
}
