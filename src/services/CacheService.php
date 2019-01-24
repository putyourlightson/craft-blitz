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
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\events\RegisterNonCacheableElementTypesEvent;
use putyourlightson\blitz\models\SettingsModel;
use putyourlightson\blitz\records\CacheRecord;
use putyourlightson\blitz\records\ElementCacheRecord;
use putyourlightson\blitz\records\ElementQueryCacheRecord;
use putyourlightson\blitz\records\ElementQueryRecord;
use yii\db\Exception;

/**
 *
 * @property string[] $nonCacheableElementTypes
 */
class CacheService extends Component
{
    // Constants
    // =========================================================================

    /**
     * @event RegisterNonCacheableElementTypesEvent
     */
    const EVENT_REGISTER_NON_CACHEABLE_ELEMENT_TYPES = 'registerNonCacheableElementTypes';

    // Properties
    // =========================================================================

    /**
     * @var string[]|null
     */
    private $_nonCacheableElementTypes;

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
     * Returns non cacheable element types.
     *
     * @return string[]
     */
    public function getNonCacheableElementTypes(): array
    {
        if ($this->_nonCacheableElementTypes !== null) {
            return $this->_nonCacheableElementTypes;
        };

        $event = new RegisterNonCacheableElementTypesEvent([
            'elementTypes' => Blitz::$settings->nonCacheableElementTypes,
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
        if (!Blitz::$settings->cacheElements) {
            return;
        }

        // Don't proceed if this is a non cacheable element type
        if (in_array(get_class($element), $this->getNonCacheableElementTypes(), true)) {
            return;
        }

        // Cast ID to integer to ensure the strict type check below works
        /** @var Element $element */
        $elementId = (int)$element->id;

        if (!in_array($elementId, $this->_elementCaches, true)) {
            $this->_elementCaches[] = $elementId;
        }
    }

    /**
     * Adds an element query cache.
     *
     * @param ElementQuery $elementQuery
     * @throws Exception
     */
    public function addElementQueryCache(ElementQuery $elementQuery)
    {
        // Don't proceed if element query caching is disabled
        if (!Blitz::$settings->cacheElementQueries) {
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

        if (!in_array($queryId, $this->_elementQueryCaches, true)) {
            $this->_elementQueryCaches[] = $queryId;
        }
    }

    /**
     * Saves the output to a URI.
     *
     * @param string $output
     * @param int $siteId
     * @param string $uri
     * @throws Exception
     */
    public function saveOutput(string $output, int $siteId, string $uri)
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

        Blitz::$plugin->driver->saveCache($output, $siteId, $uri);
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
