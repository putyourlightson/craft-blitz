<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\services;

use Craft;
use craft\base\Component;
use craft\base\ElementInterface;
use craft\db\ActiveRecord;
use craft\elements\db\ElementQuery;
use craft\events\CancelableEvent;
use craft\events\PopulateElementEvent;
use craft\helpers\Db;
use craft\helpers\StringHelper;
use craft\records\Element;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\events\SaveCacheEvent;
use putyourlightson\blitz\helpers\ElementQueryHelper;
use putyourlightson\blitz\helpers\ElementTypeHelper;
use putyourlightson\blitz\helpers\SiteUriHelper;
use putyourlightson\blitz\models\CacheOptionsModel;
use putyourlightson\blitz\models\SettingsModel;
use putyourlightson\blitz\models\SiteUriModel;
use putyourlightson\blitz\records\CacheRecord;
use putyourlightson\blitz\records\ElementCacheRecord;
use putyourlightson\blitz\records\ElementFieldCacheRecord;
use putyourlightson\blitz\records\ElementQueryCacheRecord;
use putyourlightson\blitz\records\ElementQueryRecord;
use putyourlightson\blitz\records\ElementQuerySourceRecord;
use putyourlightson\blitz\records\IncludeRecord;
use putyourlightson\blitz\records\SsiIncludeCacheRecord;
use yii\base\Event;
use yii\db\Exception;
use yii\log\Logger;

class GenerateCacheService extends Component
{
    /**
     * @event RefreshCacheEvent
     */
    public const EVENT_BEFORE_SAVE_CACHE = 'beforeSaveCache';

    /**
     * @event RefreshCacheEvent
     */
    public const EVENT_AFTER_SAVE_CACHE = 'afterSaveCache';

    /**
     * @const string
     */
    public const MUTEX_LOCK_NAME_CACHE_RECORDS = 'blitz:cacheRecords';

    /**
     * @const string
     */
    public const MUTEX_LOCK_NAME_ELEMENT_QUERY_RECORDS = 'blitz:elementQueryRecords';

    /**
     * @const string
     */
    public const MUTEX_LOCK_NAME_INCLUDE_RECORDS = 'blitz:includeRecords';

    /**
     * @const string
     */
    public const MUTEX_LOCK_NAME_SSI_INCLUDE_RECORDS = 'blitz:ssiIncludeRecords';

    /**
     * @var CacheOptionsModel
     */
    public CacheOptionsModel $options;

    /**
     * @var int[]
     */
    public array $elementCaches = [];

    /**
     * @var int[][]|bool[]
     */
    public array $elementCachesTrackFields = [];

    /**
     * @var int[]
     */
    public array $elementQueryCaches = [];

    /**
     * @var int[]
     */
    public array $ssiIncludeCaches = [];

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
        $this->elementCaches = [];
        $this->elementCachesTrackFields = [];
        $this->elementQueryCaches = [];
        $this->ssiIncludeCaches = [];
        $this->options = new CacheOptionsModel();

        // Set default attributes from the plugin settings
        $this->options->setAttributes(Blitz::$plugin->settings->getAttributes(), false);
    }

    /**
     * Registers element prepare events.
     */
    public function registerElementPrepareEvents(): void
    {
        // Register element populate event
        Event::on(ElementQuery::class, ElementQuery::EVENT_AFTER_POPULATE_ELEMENT,
            function(PopulateElementEvent $event) {
                if (Craft::$app->getResponse()->getIsOk()) {
                    $this->addElement($event->element);
                }
            }
        );

        // Register element query prepare event
        Event::on(ElementQuery::class, ElementQuery::EVENT_BEFORE_PREPARE,
            function(CancelableEvent $event) {
                if (Craft::$app->getResponse()->getIsOk()) {
                    /** @var ElementQuery $elementQuery */
                    $elementQuery = $event->sender;
                    $this->addElementQuery($elementQuery);
                }
            }
        );
    }

    /**
     * Adds an element to be cached.
     */
    public function addElement(ElementInterface $element): void
    {
        // Don’t proceed if element tracking is disabled
        if (!Blitz::$plugin->settings->trackElements || !$this->options->trackElements) {
            return;
        }

        // Check deprecated values in case they’re still being used.
        if (!Blitz::$plugin->settings->cacheElements || !$this->options->cacheElements) {
            return;
        }

        // Don’t proceed if not a cacheable element type
        if (!ElementTypeHelper::getIsCacheableElementType(get_class($element))) {
            return;
        }

        if (!in_array($element->getId(), $this->elementCaches)) {
            $this->elementCaches[] = $element->getId();
        }

        $this->_addElementTrackFields($element);
    }

    /**
     * Adds an element query to be cached.
     */
    public function addElementQuery(ElementQuery $elementQuery): void
    {
        // Don’t proceed if element query tracking is disabled
        if (!Blitz::$plugin->settings->trackElementQueries || !$this->options->trackElementQueries) {
            return;
        }

        // Check deprecated values in case they’re still being used.
        if (!Blitz::$plugin->settings->cacheElementQueries || !$this->options->cacheElementQueries) {
            return;
        }

        // Don’t proceed if not a cacheable element type
        if (!ElementTypeHelper::getIsCacheableElementType($elementQuery->elementType)) {
            return;
        }

        // Don’t proceed if the query has fixed IDs or slugs
        if (ElementQueryHelper::hasFixedIdsOrSlugs($elementQuery)) {
            return;
        }

        // Don’t proceed if the query contains an expression criteria
        if (ElementQueryHelper::containsExpressionCriteria($elementQuery)) {
            return;
        }

        // Don’t proceed if the order is random
        if (ElementQueryHelper::isOrderByRandom($elementQuery)) {
            return;
        }

        // Don’t proceed if this is a draft or revision query
        if (ElementQueryHelper::isDraftOrRevisionQuery($elementQuery)) {
            return;
        }

        // Don’t proceed if this is a relation query
        if (ElementQueryHelper::isRelationQuery($elementQuery)) {
            return;
        }

        $this->saveElementQuery($elementQuery);
    }

    /**
     * Adds an SSI include.
     */
    public function addSsiInclude(int $includeId): void
    {
        // Don’t proceed if element query caching is disabled
        if (Blitz::$plugin->settings->cachingEnabled === false) {
            return;
        }

        $this->saveSsiInclude($includeId);
    }

    /**
     * Saves an element query.
     */
    public function saveElementQuery(ElementQuery $elementQuery): void
    {
        $params = json_encode(ElementQueryHelper::getUniqueElementQueryParams($elementQuery));
        $index = $this->_createUniqueIndex($elementQuery->elementType . $params);

        // Require a mutex for the element query index to avoid doing the same operation multiple times
        $mutex = Craft::$app->getMutex();
        $lockName = self::MUTEX_LOCK_NAME_ELEMENT_QUERY_RECORDS . ':' . $index;

        if (!$mutex->acquire($lockName, Blitz::$plugin->settings->mutexTimeout)) {
            return;
        }

        // Get element query record from index or create one if it does not exist
        $queryId = ElementQueryRecord::find()
            ->select('id')
            ->where(['index' => $index])
            ->scalar();

        if (!$queryId) {
            try {
                // Use DB connection, so we can exclude audit columns when inserting
                $db = Craft::$app->getDb();

                $db->createCommand()
                    ->insert(
                        ElementQueryRecord::tableName(),
                        [
                            'index' => $index,
                            'type' => $elementQuery->elementType,
                            'params' => $params,
                        ],
                    )
                    ->execute();

                $queryId = $db->getLastInsertID();

                $this->saveElementQuerySources($elementQuery, $queryId);
            } catch (Exception $exception) {
                Blitz::$plugin->log($exception->getMessage(), [], Logger::LEVEL_ERROR);
            }
        }

        if ($queryId && !in_array($queryId, $this->elementQueryCaches)) {
            $this->elementQueryCaches[] = $queryId;
        }

        $mutex->release($lockName);
    }

    /**
     * Saves an element query's sources.
     */
    public function saveElementQuerySources(ElementQuery $elementQuery, string $queryId): void
    {
        $db = Craft::$app->getDb();

        $sourceIdAttribute = ElementTypeHelper::getSourceIdAttribute($elementQuery->elementType);
        $sourceIds = $sourceIdAttribute ? $elementQuery->{$sourceIdAttribute} : null;

        // Normalize source IDs
        $sourceIds = ElementQueryHelper::getNormalizedElementQueryIdParam($sourceIds);

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
                ->insert(
                    ElementQuerySourceRecord::tableName(),
                    [
                        'sourceId' => $sourceId,
                        'queryId' => $queryId,
                    ],
                )
                ->execute();
        }
    }

    /**
     * Saves an include.
     */
    public function saveInclude(int $siteId, string $template, array $params): ?array
    {
        $params = json_encode($params);
        $index = $this->_createUniqueIndex($siteId . $template . $params);

        // Require a mutex to avoid doing the same operation multiple times
        $mutex = Craft::$app->getMutex();
        $lockName = self::MUTEX_LOCK_NAME_INCLUDE_RECORDS . ':' . $index;

        if (!$mutex->acquire($lockName, Blitz::$plugin->settings->mutexTimeout)) {
            return null;
        }

        // Get record or create one if it does not exist
        $includeId = IncludeRecord::find()
            ->select('id')
            ->where(['index' => $index])
            ->scalar();

        if (!$includeId) {
            try {
                // Use DB connection, so we can exclude audit columns when inserting
                $db = Craft::$app->getDb();

                $db->createCommand()
                    ->insert(
                        IncludeRecord::tableName(),
                        [
                            'index' => $index,
                            'siteId' => $siteId,
                            'template' => $template,
                            'params' => $params,
                        ],
                    )
                    ->execute();

                $includeId = $db->getLastInsertID();
            } catch (Exception $exception) {
                Blitz::$plugin->log($exception->getMessage(), [], Logger::LEVEL_ERROR);
            }
        }

        $mutex->release($lockName);

        return $includeId ? [$includeId, $index] : null;
    }

    /**
     * Saves an SSI include.
     */
    public function saveSsiInclude(int $includeId): void
    {
        if (!in_array($includeId, $this->ssiIncludeCaches)) {
            $this->ssiIncludeCaches[] = $includeId;
        }
    }

    /**
     * Saves the content for a site URI to the cache.
     */
    public function save(string $content, SiteUriModel $siteUri): ?string
    {
        if (!$this->options->cachingEnabled) {
            return null;
        }

        // Don’t cache if the output contains any transform generation URLs
        // https://github.com/putyourlightson/craft-blitz/issues/125
        if (StringHelper::contains(stripslashes($content), 'assets/generate-transform')) {
            Blitz::$plugin->debug('Page not cached because it contains transform generation URLs. Consider setting the `generateTransformsBeforePageLoad` general config setting to `true` to fix this.', [], $siteUri->getUrl());

            return null;
        }

        $mutex = Craft::$app->getMutex();
        $lockName = self::MUTEX_LOCK_NAME_CACHE_RECORDS;

        if (!$mutex->acquire($lockName, Blitz::$plugin->settings->mutexTimeout)) {
            Blitz::$plugin->debug('Page not cached because a `{lockName}` mutex could not be acquired.', [
                'lockName' => $lockName,
            ], $siteUri->getUrl());

            return null;
        }

        $db = Craft::$app->getDb();

        $cacheValue = $siteUri->toArray();

        // Delete cache records so we get a fresh cache.
        CacheRecord::deleteAll($cacheValue);

        // Don’t paginate URIs that are already paginated.
        $paginate = SiteUriHelper::isPaginatedUri($siteUri->uri) ? null : $this->options->paginate;

        $cacheValue = array_merge($cacheValue, [
            'paginate' => $paginate,
            'expiryDate' => Db::prepareDateForDb($this->options->expiryDate),
        ]);

        $db->createCommand()
            ->insert(CacheRecord::tableName(), $cacheValue)
            ->execute();

        $cacheId = (int)$db->getLastInsertID();

        if (!empty($this->elementCaches)) {
            $this->_batchInsertElementCaches($cacheId);
        }

        if (!empty($this->elementQueryCaches)) {
            $this->_batchInsertCaches(
                $cacheId,
                $this->elementQueryCaches,
                ElementQueryRecord::tableName(),
                ElementQueryCacheRecord::tableName(),
                'queryId',
            );
        }

        if (!empty($this->ssiIncludeCaches)) {
            $this->_batchInsertCaches(
                $cacheId,
                $this->ssiIncludeCaches,
                IncludeRecord::tableName(),
                SsiIncludeCacheRecord::tableName(),
                'includeId',
            );
        }

        // Save cache tags
        if (!empty($this->options->tags)) {
            Blitz::$plugin->cacheTags->saveTags($this->options->tags, $cacheId);
        }

        if (!Blitz::$plugin->cacheRequest->getIsCachedInclude()) {
            $outputComments = $this->options->outputComments === true
                || $this->options->outputComments === SettingsModel::OUTPUT_COMMENTS_CACHED;

            // Append cached by comment if allowed and has HTML mime type
            if ($outputComments && SiteUriHelper::hasHtmlMimeType($siteUri)) {
                $content .= '<!-- Cached by Blitz on ' . date('c') . ' -->';
            }
        }

        $this->saveOutput($content, $siteUri, $this->options->getCacheDuration());

        $this->reset();

        $mutex->release($lockName);

        return $content;
    }

    /**
     * Saves the output for a site URI.
     */
    public function saveOutput(string $output, SiteUriModel $siteUri, int $duration = null): void
    {
        $event = new SaveCacheEvent([
            'output' => $output,
            'siteUri' => $siteUri,
            'duration' => $duration,
        ]);
        $this->trigger(self::EVENT_BEFORE_SAVE_CACHE, $event);

        if (!$event->isValid) {
            Blitz::$plugin->debug('Page not cached because the `{event}` event is not valid.', [
                'event' => self::EVENT_BEFORE_SAVE_CACHE,
            ], $siteUri->getUrl());

            return;
        }

        Blitz::$plugin->cacheStorage->save($event->output, $event->siteUri, $event->duration);

        if ($this->hasEventHandlers(self::EVENT_AFTER_SAVE_CACHE)) {
            $this->trigger(self::EVENT_AFTER_SAVE_CACHE, $event);
        }
    }

    /**
     * Returns an element’s field IDs of the provided field handles.
     *
     * @param string[] $handles
     * @return int[]
     */
    public function getFieldIdsFromHandles(ElementInterface $element, array $handles): array
    {
        $fieldIds = [];

        // Get fields from the layout, as field handles are not unique.
        $fieldLayout = $element->getFieldLayout();

        foreach ($handles as $handle) {
            $field = $fieldLayout->getFieldByHandle($handle);

            if ($field !== null) {
                $fieldIds[] = $field->id;
            }
        }

        return $fieldIds;
    }

    /**
     * Adds the custom fields to track for an element ID.
     */
    private function _addElementTrackFields(ElementInterface $element): void
    {
        if ($this->options->trackCustomFields === true) {
            $trackFields = true;
        } elseif ($this->options->trackCustomFields === false) {
            $trackFields = [];
        } elseif (is_string($this->options->trackCustomFields)) {
            $trackFields = StringHelper::split($this->options->trackCustomFields);
        } else {
            $trackFields = $this->options->trackCustomFields;
        }

        if (is_array($trackFields)) {
            $trackFields = $this->getFieldIdsFromHandles($element, $trackFields);
        }

        $this->elementCachesTrackFields[$element->id] = $trackFields;
    }

    /**
     * Batch inserts element caches into the database.
     */
    private function _batchInsertElementCaches(int $cacheId): void
    {
        $trackAllFields = [];
        $trackFields = [];

        foreach ($this->elementCachesTrackFields as $elementId => $elementCacheTrackFields) {
            $trackAllFields[$elementId] = $elementCacheTrackFields === true;

            if (is_array($elementCacheTrackFields) && !empty($elementCacheTrackFields)) {
                $trackFields[$elementId] = $elementCacheTrackFields;
            }
        }

        $this->_batchInsertCaches(
            $cacheId,
            $this->elementCaches,
            Element::tableName(),
            ElementCacheRecord::tableName(),
            'elementId',
            $trackAllFields,
            'trackAllFields',
        );

        if (!empty($trackFields)) {
            $this->_batchInsertCaches(
                $cacheId,
                $this->elementCaches,
                Element::tableName(),
                ElementFieldCacheRecord::tableName(),
                'elementId',
                $trackFields,
                'fieldId',
            );
        }
    }

    /**
     * Batch inserts cache values into the database.
     */
    private function _batchInsertCaches(int $cacheId, array $ids, string $checkTable, string $insertTable, string $columnName, array $extraValues = null, string $extraColumnName = null): void
    {
        // Get valid IDs by selecting only records with existing IDs
        $validIds = ActiveRecord::find()
            ->select('id')
            ->from($checkTable)
            ->where(['id' => $ids])
            ->column();

        $values = [];
        foreach ($validIds as $id) {
            if ($extraValues === null) {
                $values[] = [$cacheId, $id];
            } elseif (isset($extraValues[$id])) {
                if (is_array($extraValues[$id])) {
                    foreach ($extraValues[$id] as $extraValue) {
                        $values[] = [$cacheId, $id, $extraValue];
                    }
                } else {
                    $values[] = [$cacheId, $id, $extraValues[$id]];
                }
            }
        }

        $columns = ['cacheId', $columnName];
        if ($extraColumnName !== null) {
            $columns[] = $extraColumnName;
        }

        Craft::$app->getDb()->createCommand()->batchInsert(
            $insertTable,
            $columns,
            $values,
        )
        ->execute();
    }

    /**
     * Creates a unique index for quicker indexing and less storage.
     */
    private function _createUniqueIndex(string $value): string
    {
        return sprintf('%u', crc32($value));
    }
}
