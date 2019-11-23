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
use yii\db\Exception;
use yii\log\Logger;

class GenerateCacheService extends Component
{
    // Constants
    // =========================================================================

    /**
     * @const string
     */
    const MUTEX_LOCK_NAME_QUERY = 'blitz:query';

    /**
     * @const string
     */
    const MUTEX_LOCK_NAME_SITE_URI = 'blitz:siteUri';

    // Properties
    // =========================================================================

    /**
     * @var CacheOptionsModel
     */
    public $options;

    /**
     * @var int[]
     */
    private $_elementCaches = [];

    /**
     * @var int[]
     */
    private $_elementQueryCaches = [];

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

        if (!in_array($element->getId(), $this->_elementCaches)) {
            $this->_elementCaches[] = $element->getId();
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

        $params = json_encode($this->_getUniqueElementQueryParams($elementQuery));

        // Create a unique index from the element type and parameters for quicker indexing and less storage
        $index = sprintf('%u', crc32($elementQuery->elementType.$params));

        $mutex = Craft::$app->getMutex();
        $lockName = self::MUTEX_LOCK_NAME_QUERY.':'.$index;

        if (!$mutex->acquire($lockName, Blitz::$plugin->settings->mutexTimeout)) {
            return;
        }

        // Use DB connection so we can insert and exclude audit columns
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
            }
            catch (Exception $e) {
                Craft::getLogger()->log($e->getMessage(), Logger::LEVEL_ERROR, 'blitz');
            }
        }

        if ($queryId && !in_array($queryId, $this->_elementQueryCaches)) {
            $this->_elementQueryCaches[] = $queryId;
        }

        $mutex->release($lockName);
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

        // Don't cache if there are any transform generation URLs in the body
        // https://github.com/putyourlightson/craft-blitz/issues/125
        if (StringHelper::contains(stripslashes($output), 'assets/generate-transform')) {
            return;
        }

        $mutex = Craft::$app->getMutex();
        $lockName = self::MUTEX_LOCK_NAME_SITE_URI.':'.$siteUri->siteId.'-'.$siteUri->uri;

        if (!$mutex->acquire($lockName, Blitz::$plugin->settings->mutexTimeout)) {
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

        $cacheId = $db->getLastInsertID();

        // Add element caches to database
        $values = [];

        foreach ($this->_elementCaches as $elementId) {
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

        foreach ($this->_elementQueryCaches as $queryId) {
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

        /** @var ElementInterface $elementType */
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

        // Convert the query parameter values recursively
        array_walk_recursive($params, [$this, '_convertQueryParams']);

        return $params;
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

            if (is_int($value)) {
                return true;
            }

            if (is_string($value) && stripos($value, 'not') !== 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Converts query parameter values to more concise formats.
     *
     * @param mixed $value
     */
    private function _convertQueryParams(&$value)
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
