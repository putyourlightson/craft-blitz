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
use putyourlightson\blitz\records\ElementQueryCacheRecord;
use putyourlightson\blitz\records\ElementQueryRecord;
use putyourlightson\blitz\records\ElementQuerySourceRecord;
use putyourlightson\blitz\records\RelatedCacheRecord;
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
     * @var CacheOptionsModel
     */
    public CacheOptionsModel $options;

    /**
     * @var int[]
     */
    public array $elementCaches = [];

    /**
     * @var int[]
     */
    public array $elementQueryCaches = [];

    /**
     * @var string|null
     */
    public ?string $relatedUri = null;

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
        $this->elementQueryCaches = [];
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
     */
    public function addElementQuery(ElementQuery $elementQuery): void
    {
        // Don't proceed if element query caching is disabled
        if (!Blitz::$plugin->settings->cacheElementQueries || !$this->options->cacheElementQueries) {
            return;
        }

        // Don't proceed if not a cacheable element type
        if (!ElementTypeHelper::getIsCacheableElementType($elementQuery->elementType)) {
            return;
        }

        // Don't proceed if the query has fixed IDs or slugs
        if (ElementQueryHelper::hasFixedIdsOrSlugs($elementQuery)) {
            return;
        }

        // Don't proceed if the query contains an expression criteria
        if (ElementQueryHelper::containsExpressionCriteria($elementQuery)) {
            return;
        }

        // Don't proceed if the order is random
        if (ElementQueryHelper::isOrderByRandom($elementQuery)) {
            return;
        }

        // Don't proceed if this is a draft or revision query
        if (ElementQueryHelper::isDraftOrRevisionQuery($elementQuery)) {
            return;
        }

        // Don't proceed if this is a relation query
        if (ElementQueryHelper::isRelationQuery($elementQuery)) {
            return;
        }

        $this->saveElementQuery($elementQuery);
    }

    /**
     * Saves an element query.
     */
    public function saveElementQuery(ElementQuery $elementQuery): void
    {
        $params = json_encode(ElementQueryHelper::getUniqueElementQueryParams($elementQuery));

        // Create a unique index from the element type and parameters for quicker indexing and less storage
        $index = sprintf('%u', crc32($elementQuery->elementType . $params));

        // Require a mutex for the element query index to avoid doing the same operation multiple times
        $mutex = Craft::$app->getMutex();
        $lockName = self::MUTEX_LOCK_NAME_ELEMENT_QUERY_RECORDS . ':' . $index;

        if (!$mutex->acquire($lockName, Blitz::$plugin->settings->mutexTimeout)) {
            return;
        }

        // Use DB connection, so we can exclude audit columns when inserting
        $db = Craft::$app->getDb();

        // Get element query record from index or create one if it does not exist
        $queryId = ElementQueryRecord::find()
            ->select('id')
            ->where(['index' => $index])
            ->scalar();

        if (!$queryId) {
            try {
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
            }
            catch (Exception $exception) {
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
     * Saves the content for a site URI to the cache.
     */
    public function save(string $content, SiteUriModel $siteUri): ?string
    {
        if (!$this->options->cachingEnabled) {
            return null;
        }

        // Don't cache if the output contains any transform generation URLs
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

        // Don't paginate URIs that are already paginated.
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
            $this->_batchInsertCaches($cacheId,
                $this->elementCaches,
                Element::tableName(),
                ElementCacheRecord::tableName(),
                'elementId'
            );
        }

        if (!empty($this->elementQueryCaches)) {
            $this->_batchInsertCaches($cacheId,
                $this->elementQueryCaches,
                ElementQueryRecord::tableName(),
                ElementQueryCacheRecord::tableName(),
                'queryId'
            );
        }

        if ($this->relatedUri !== null) {
            $this->_insertRelatedCache($cacheId, $this->relatedUri);
        }

        // Save cache tags
        if (!empty($this->options->tags)) {
            Blitz::$plugin->cacheTags->saveTags($this->options->tags, $cacheId);
        }

        if (!Blitz::$plugin->cacheRequest->getIsStaticInclude()) {
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
     * Batch inserts cache values into the database.
     */
    private function _batchInsertCaches(int $cacheId, array $ids, string $checkTable, string $insertTable, string $columnName): void
    {
        // Get values by selecting only records with existing IDs
        $values = ActiveRecord::find()
            ->select('id')
            ->from($checkTable)
            ->where(['id' => $ids])
            ->column();

        foreach ($values as $key => $value) {
            $values[$key] = [$cacheId, $value];
        }

        Craft::$app->getDb()->createCommand()->batchInsert(
            $insertTable,
            ['cacheId', $columnName],
            $values,
        )
        ->execute();
    }

    /**
     * Inserts a related cache ID into the database.
     */
    private function _insertRelatedCache(int $cacheId, string $uri): void
    {
        $relatedCacheId = CacheRecord::find()
            ->select('id')
            ->where(['uri' => $uri])
            ->scalar();

        if ($relatedCacheId) {
            $relatedCacheRecord = new RelatedCacheRecord();
            $relatedCacheRecord->cacheId = $cacheId;
            $relatedCacheRecord->relatedCacheId = $relatedCacheId;
            $relatedCacheRecord->save();
        }
    }
}
