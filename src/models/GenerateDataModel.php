<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\models;

use craft\base\ElementInterface;
use putyourlightson\blitz\helpers\FieldHelper;

/**
 * Used for storing, manipulating and returning data during a generate cache
 * request.
 *
 * @property-read int[] $elementIds
 * @property-read int[][] $elementIndexedTrackFields
 * @property-read int[] $elementQueryIds
 * @property-read int[] $ssiIncludeIds
 * @property bool $hasIncludes
 *
 * @since 4.4.0
 */
class GenerateDataModel extends BaseDataModel
{
    /**
     * @var array{
     *          elements: array{
     *              elementIds: array<int, bool>,
     *              trackFields: array<int, array<string, bool>>,
     *          },
     *          elementQueries: array<string, array<int, array<string, mixed>>>,
     *          ssiIncludeIds: array<int, bool>,
     *          hasIncludes: bool,
     *      }
     */
    public array $data = [
        'elements' => [
            'elementIds' => [],
            'trackFields' => [],
        ],
        'elementQueries' => [],
        'ssiIncludeIds' => [],
        'hasIncludes' => false,
    ];

    /**
     * @return int[]
     */
    public function getElementIds(): array
    {
        return $this->getKeysAsValues(['elements', 'elementIds']);
    }

    /**
     * @return int[][]
     */
    public function getElementIndexedTrackFields(): array
    {
        $indexedFields = [];
        $trackFields = $this->data['elements']['trackFields'];

        foreach ($trackFields as $elementId => $fields) {
            $fieldHandles = array_keys($fields);
            $fieldIds = FieldHelper::getFieldIdsFromHandles($fieldHandles);
            $indexedFields[$elementId] = $fieldIds;
        }

        return $indexedFields;
    }

    /**
     * Returns element query IDs without redundant queries.
     *
     * @return int[]
     */
    public function getElementQueryIds(): array
    {
        $elementQueryIds = [];

        foreach ($this->data['elementQueries'] as $elementQueries) {
            foreach ($elementQueries as $queryId => $params) {
                $otherElementQueries = array_filter($elementQueries, fn($key) => $key !== $queryId, ARRAY_FILTER_USE_KEY);
                if (!$this->elementQueriesWithHigherLimitExist($params, $otherElementQueries)) {
                    $elementQueryIds[] = $queryId;
                }
            }
        }

        return $elementQueryIds;
    }

    /**
     * @return int[]
     */
    public function getSsiIncludeIds(): array
    {
        return $this->getKeysAsValues(['ssiIncludeIds']);
    }

    public function getHasIncludes(): bool
    {
        return $this->data['hasIncludes'] ?? false;
    }

    public function addElementId(int $elementId): void
    {
        $this->data['elements']['elementIds'][$elementId] = true;
    }

    public function addElementIds(array $elementIds): void
    {
        foreach ($elementIds as $elementId) {
            $this->addElementId($elementId);
        }
    }

    public function addElement(ElementInterface $element): void
    {
        if ($element->id === null) {
            return;
        }

        $this->addElementId($element->id);
    }

    public function addElementTrackField(ElementInterface $element, $field): void
    {
        $this->data['elements']['trackFields'][$element->id][$field] = true;
    }

    public function addElementQuery(int $elementQueryId, string $elementType, array $params): void
    {
        $this->data['elementQueries'][$elementType][$elementQueryId] = $params;
    }

    public function addSsiIncludes(int $ssiIncludeId): void
    {
        $this->data['ssiIncludeIds'][$ssiIncludeId] = true;
        $this->setHasIncludes();
    }

    public function setHasIncludes(bool $value = true): void
    {
        $this->data['hasIncludes'] = $value;
    }

    /**
     * Returns whether one or more element queries with the same params and a higher limit exist.
     */
    private function elementQueriesWithHigherLimitExist(array $params, array $otherElementQueries): bool
    {
        if (!isset($params['limit'])) {
            return false;
        }

        foreach ($otherElementQueries as $otherParams) {
            if ($this->elementQueryWithHigherLimitExists($params, $otherParams)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns whether an element query with the same params and a higher limit exists.
     */
    private function elementQueryWithHigherLimitExists(array $params, array $otherParams): bool
    {
        $keys = array_diff(array_keys($params + $otherParams), ['limit', 'offset']);

        foreach ($keys as $key) {
            if (!isset($params[$key]) || !isset($otherParams[$key]) || $params[$key] !== $otherParams[$key]) {
                return false;
            }
        }

        if (isset($otherParams['limit'])) {
            $limitSum = $params['limit'] + ($params['offset'] ?? 0);
            $otherLimitSum = $otherParams['limit'] + ($otherParams['offset'] ?? 0);

            if ($limitSum > $otherLimitSum) {
                return false;
            }

            // If the limit sums are equal then the limit takes precedence.
            if ($limitSum === $otherLimitSum && $params['limit'] > $otherParams['limit']) {
                return false;
            }
        }

        return true;
    }
}
