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

class GenerateCacheService extends Component
{
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

        /** @var Element $element */
        if (!in_array($element->id, $this->_elementCaches)) {
            $this->_elementCaches[] = $element->id;
        }
    }

    /**
     * Adds an element query to be cached.
     *
     * @param ElementQuery $elementQuery
     * @throws Exception
     */
    public function addElementQuery(ElementQuery $elementQuery)
    {
        // Don't proceed if element query caching is disabled
        if (!Blitz::$plugin->settings->cacheElementQueries || !$this->options->cacheElementQueries) {
            return;
        }

        // Don't proceed if not a cacheable element type
        if (empty($elementQuery->elementType) || !ElementTypeHelper::getIsCacheableElementType($elementQuery->elementType)) {
            return;
        }

        // Don't proceed if the query has fixed IDs or UIDs
        if (
            $this->_isFixedId($elementQuery->id)
            || $this->_isFixedId($elementQuery->where['elements.id'])
            || $this->_isFixedId($elementQuery->uid)
            || $this->_isFixedId($elementQuery->where['elements.uid'])
        ) {
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
        $lockName = 'blitz:query:'.$index;

        if (!$mutex->acquire($lockName)) {
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
            $db->createCommand()
                ->insert(ElementQueryRecord::tableName(), [
                    'index' => $index,
                    'type' => $elementQuery->elementType,
                    'params' => $params,
                ], false)
                ->execute();

            $queryId = $db->getLastInsertID();
        }

        if (!in_array($queryId, $this->_elementQueryCaches)) {
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
        $lockName = 'blitz:save:'.$siteUri->siteId.'-'.$siteUri->uri;

        if (!$mutex->acquire($lockName)) {
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
     * @param string|ElementInterface $elementType
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

        // Convert the query parameter values recursively
        array_walk_recursive($params, [$this, '_convertQueryParams']);

        return $params;
    }

    /**
     * Returns whether the value is a fixed ID.
     *
     * @param mixed $value
     */
    private function _isFixedId($value)
    {
        if (is_string($value) && stripos($value, 'not') !== 0) {
            return true;
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
        if ($value instanceof Element) {
            $value = $value->id;
        }

        // Convert DateTime objects to Unix timestamp
        if ($value instanceof DateTime) {
            $value = $value->getTimestamp();
        }
    }
}
