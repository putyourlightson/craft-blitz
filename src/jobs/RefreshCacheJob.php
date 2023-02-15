<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\jobs;

use Craft;
use craft\base\Element;
use craft\base\ElementInterface;
use craft\elements\db\ElementQuery;
use craft\helpers\Json;
use craft\helpers\StringHelper;
use craft\queue\BaseJob;
use Exception;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\helpers\ElementTypeHelper;
use putyourlightson\blitz\helpers\SiteUriHelper;
use putyourlightson\blitz\records\ElementQueryCacheRecord;
use putyourlightson\blitz\records\ElementQueryRecord;
use yii\log\Logger;
use yii\queue\RetryableJobInterface;

/**
 * @property-read int $ttr
 */
class RefreshCacheJob extends BaseJob implements RetryableJobInterface
{
    /**
     * @var int[]
     */
    public array $cacheIds = [];

    /**
     * @var array<string, array{
     *          elements: array<int, int[]|bool>,
     *          sourceIds: int[],
     *      }>
     */
    public array $elements = [];

    /**
     * @var bool
     */
    public bool $forceClear = false;

    /**
     * @var bool
     */
    public bool $forceGenerate = false;

    /**
     * @inheritdoc
     */
    public function getTtr(): int
    {
        return Blitz::$plugin->settings->queueJobTtr;
    }

    /**
     * @inheritdoc
     */
    public function canRetry($attempt, $error): bool
    {
        return $attempt < Blitz::$plugin->settings->maxRetryAttempts;
    }

    /**
     * @inheritdoc
     */
    public function execute($queue): void
    {
        // Merge in element cache IDs
        foreach ($this->elements as $element) {
            $elementCacheIds = Blitz::$plugin->refreshCache->getElementCacheIds($element['elements']);
            $this->cacheIds = array_merge($this->cacheIds, $elementCacheIds);
        }

        $clearCache = Blitz::$plugin->settings->clearOnRefresh($this->forceClear);

        // If clear cache is enabled then clear the site URIs early
        if ($clearCache) {
            $siteUris = SiteUriHelper::getCachedSiteUris($this->cacheIds);
            Blitz::$plugin->clearCache->clearUris($siteUris);
        }

        // Merge in cache IDs that match any source tags
        foreach ($this->elements as $elementType => $element) {
            $this->cacheIds = array_unique(array_merge(
                $this->cacheIds,
                $this->_getSourceTagCacheIds($elementType, $element['sourceIds'])
            ));
        }

        // Merge in cache IDs that match element query results
        /** @var ElementInterface|string $elementType */
        foreach ($this->elements as $elementType => $elements) {
            // If we have elements then loop through element queries to check for matches
            if (count($elements)) {
                $elementIds = array_keys($elements['elements']);
                $fieldIds = $this->_getCombinedChangedByFields($elements['elements']);

                $elementQueryRecords = Blitz::$plugin->refreshCache->getElementTypeQueries(
                    $elementType, $elements['sourceIds'], $fieldIds, $this->cacheIds
                );

                $total = count($elementQueryRecords);

                if ($total > 0) {
                    $count = 0;

                    // Use sets and the splat operator rather than array_merge for performance
                    // https://github.com/kalessil/phpinspectionsea/blob/master/docs/performance.md#slow-array-function-used-in-loop
                    $elementQueryCacheIdSets = [];

                    foreach ($elementQueryRecords as $elementQueryRecord) {
                        // Merge in element query cache IDs
                        $elementQueryCacheIdSets[] = $this->_getElementQueryCacheIds(
                            $elementQueryRecord, $elementIds, $this->cacheIds
                        );

                        $count++;
                        $this->setProgress($queue, $count / $total,
                            Craft::t('blitz', 'Checking {count} of {total} {elementType} queries.', [
                                'count' => $count,
                                'total' => $total,
                                // Don't use `lowerDisplayName` which was only introduced in Craft 3.3.17
                                // https://github.com/putyourlightson/craft-blitz/issues/285
                                'elementType' => StringHelper::toLowerCase($elementType::displayName()),
                            ])
                        );
                    }

                    $elementQueryCacheIds = array_merge(...$elementQueryCacheIdSets);
                    $this->cacheIds = array_merge($this->cacheIds, $elementQueryCacheIds);
                }
            }
        }

        // If clear cache is disabled then expire the cache IDs.
        if (!$clearCache) {
            Blitz::$plugin->refreshCache->expireCacheIds($this->cacheIds);
        }

        $siteUris = SiteUriHelper::getCachedSiteUris($this->cacheIds);

        // Merge in site URIs of element IDs to ensure that uncached elements are also generated
        /** @var ElementInterface $elementType */
        foreach ($this->elements as $elementType => $element) {
            if ($elementType::hasUris()) {
                $elementIds = array_keys($element['elements']);
                $siteUris = array_merge($siteUris, SiteUriHelper::getElementSiteUris($elementIds));
            }
        }

        $siteUris = array_unique($siteUris, SORT_REGULAR);

        Blitz::$plugin->refreshCache->refreshSiteUris($siteUris, $this->forceClear, $this->forceGenerate);
    }

    /**
     * @inheritdoc
     */
    protected function defaultDescription(): string
    {
        return Craft::t('blitz', 'Refreshing Blitz cache');
    }

    /**
     * Returns cache IDs that match any special source tags.
     *
     * @return int[]
     */
    private function _getSourceTagCacheIds(string $elementType, array $sourceIds): array
    {
        $sourceIdAttribute = ElementTypeHelper::getSourceIdAttribute($elementType);

        $tags = [$sourceIdAttribute . ':*'];

        foreach ($sourceIds as $sourceId) {
            $tags[] = $sourceIdAttribute . ':' . $sourceId;
        }

        return Blitz::$plugin->cacheTags->getCacheIds($tags);
    }

    /**
     * Returns an array of combined changed field IDs from multiple elements.
     *
     * @param int[][]|bool[] $elements
     * @return int[]|bool
     */
    private function _getCombinedChangedByFields(array $elements): array|bool
    {
        $fieldIds = [];

        foreach ($elements as $changedByFields) {
            if (empty($changedByFields)) {
                return [];
            } elseif ($changedByFields === true) {
                $fieldIds = true;
            } elseif (is_array($fieldIds)) {
                $fieldIds = array_merge($fieldIds, $changedByFields);
            }
        }

        if (is_array($fieldIds)) {
            return array_values(array_unique($fieldIds));
        }

        return $fieldIds;
    }

    /**
     * Returns cache IDs from a given entry query that contains the provided
     * element IDs, ignoring the provided cache IDs.
     *
     * @param int[] $elementIds
     * @param int[] $ignoreCacheIds
     * @return int[]
     */
    private function _getElementQueryCacheIds(ElementQueryRecord $elementQueryRecord, array $elementIds, array $ignoreCacheIds): array
    {
        // Ensure class still exists as a plugin may have been removed since being saved
        if (!class_exists($elementQueryRecord->type)) {
            return [];
        }

        $cacheIds = [];

        /** @var Element $elementType */
        $elementType = $elementQueryRecord->type;

        /** @var ElementQuery $elementQuery */
        $elementQuery = $elementType::find();

        $params = Json::decodeIfJson($elementQueryRecord->params);

        // If json decode failed
        if (!is_array($params)) {
            return [];
        }

        foreach ($params as $key => $val) {
            $elementQuery->{$key} = $val;
        }

        // If the element query has an offset then add it to the limit and make it null
        if ($elementQuery->offset) {
            if ($elementQuery->limit) {
                // Cast values to integers before trying to add them, as they may have been set to strings
                $elementQuery->limit((int)$elementQuery->limit + (int)$elementQuery->offset);
            }

            $elementQuery->offset(null);
        }

        $elementQueryIds = [];

        // Execute the element query, ignoring any exceptions.
        try {
            $elementQueryIds = $elementQuery->ids();
        } catch (Exception $exception) {
            Blitz::$plugin->log('Element query with ID `' . $elementQueryRecord->id . '` could not be executed: ' . $exception->getMessage(), [], Logger::LEVEL_ERROR);
        }

        // If one or more of the element IDs are in the element query's IDs
        if (!empty(array_intersect($elementIds, $elementQueryIds))) {
            // Get related element query cache records
            /** @var ElementQueryCacheRecord[] $elementQueryCacheRecords */
            $elementQueryCacheRecords = $elementQueryRecord->getElementQueryCaches()->all();

            // Add cache IDs to the array that do not already exist
            foreach ($elementQueryCacheRecords as $elementQueryCacheRecord) {
                if (!in_array($elementQueryCacheRecord->cacheId, $ignoreCacheIds)) {
                    $cacheIds[] = $elementQueryCacheRecord->cacheId;
                }
            }
        }

        return $cacheIds;
    }
}
