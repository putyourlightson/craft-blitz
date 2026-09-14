<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\models;

use craft\base\ElementInterface;
use craft\models\FieldLayout;
use putyourlightson\blitz\helpers\FieldHelper;

/**
 * Used for storing, manipulating and returning data during a generate cache
 * request.
 *
 * @property-read int[] $elementIds
 * @property-read array<int, int[]> $elementSiteIds
 * @property-read array<int, string[]> $elementTrackFields
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
     *              siteIds: array<int, array<int, bool>>,
     *              siteTrackFields: array<int, array<string, array<int, bool>>>,
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
            'siteIds' => [],
            'siteTrackFields' => [],
        ],
        'elementQueries' => [],
        'ssiIncludeIds' => [],
        'hasIncludes' => false,
    ];

    /**
     * Returns element IDs, optionally limited to a site, including dependencies with an unknown site.
     *
     * @return int[]
     */
    public function getElementIds(?int $siteId = null): array
    {
        if ($siteId !== null) {
            $elementIds = [];
            foreach ($this->getElementSiteIds() as $elementId => $siteIds) {
                if (in_array($siteId, $siteIds, true) || in_array(BaseDataModel::SITE_ID_ANY, $siteIds, true)) {
                    $elementIds[] = $elementId;
                }
            }

            return $elementIds;
        }

        return $this->getKeysAsValues(['elements', 'elementIds']);
    }

    /**
     * Returns tracked site IDs for each element. SITE_ID_ANY represents an unknown site.
     *
     * @return array<int, int[]>
     * @since 5.13.0
     */
    public function getElementSiteIds(): array
    {
        $siteIds = [];

        foreach ($this->getElementIds() as $elementId) {
            $siteIds[$elementId] = array_keys($this->data['elements']['siteIds'][$elementId] ?? self::SITE_IDS_ANY);
        }

        return $siteIds;
    }

    /**
     * Returns tracked fields, optionally limited to a site, including dependencies with an unknown site.
     *
     * @return array<int, string[]>
     */
    public function getElementTrackFields(?int $siteId = null): array
    {
        $trackFields = [];
        $elementFields = $this->data['elements']['trackFields'];

        if ($siteId !== null) {
            foreach ($elementFields as $elementId => $fields) {
                $elementFields[$elementId] = array_filter($fields, function(string $fieldInstanceUid) use ($elementId, $siteId) {
                    $siteIds = $this->data['elements']['siteTrackFields'][$elementId][$fieldInstanceUid] ?? self::SITE_IDS_ANY;

                    return isset($siteIds[$siteId]) || isset($siteIds[BaseDataModel::SITE_ID_ANY]);
                }, ARRAY_FILTER_USE_KEY);
                if (empty($elementFields[$elementId])) {
                    unset($elementFields[$elementId]);
                }
            }
        }

        foreach ($elementFields as $elementId => $fields) {
            $trackFields[$elementId] = array_keys($fields);
        }

        return $trackFields;
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
        return $this->data['hasIncludes'];
    }

    public function addElementId(int $elementId, ?int $siteId = null): void
    {
        if (isset($this->data['elements']['elementIds'][$elementId]) && !isset($this->data['elements']['siteIds'][$elementId])) {
            $this->data['elements']['siteIds'][$elementId][BaseDataModel::SITE_ID_ANY] = true;
        }
        $this->data['elements']['elementIds'][$elementId] = true;
        $this->data['elements']['siteIds'][$elementId][$siteId ?? BaseDataModel::SITE_ID_ANY] = true;
    }

    public function addElementIds(array $elementIds, ?int $siteId = null): void
    {
        foreach ($elementIds as $elementId) {
            $this->addElementId($elementId, $siteId);
        }
    }

    public function addElement(ElementInterface $element): void
    {
        if ($element->id === null) {
            return;
        }

        $this->addElementId($element->id, $element->siteId);
    }

    public function addElementIdsTrackField(array $elementIds, FieldLayout $fieldLayout, $handle, ?int $siteId = null): void
    {
        $fieldInstanceUid = FieldHelper::getFieldInstanceUidForFieldLayout($fieldLayout, $handle);

        if ($fieldInstanceUid === null) {
            return;
        }

        foreach ($elementIds as $elementId) {
            $this->addTrackedField($elementId, $fieldInstanceUid, $siteId);
        }
    }

    public function addElementTrackField(ElementInterface $element, $handle): void
    {
        if ($element->id === null) {
            return;
        }

        $fieldInstanceUid = FieldHelper::getFieldInstanceUidForElement($element, $handle);

        if ($fieldInstanceUid !== null) {
            $this->addTrackedField($element->id, $fieldInstanceUid, $element->siteId);
        }
    }

    /**
     * @return array<int, array<string, int[]>>
     * @since 5.13.0
     */
    public function getElementFieldSiteIds(): array
    {
        $sites = [];
        foreach ($this->getElementTrackFields() as $elementId => $fields) {
            foreach ($fields as $field) {
                $sites[$elementId][$field] = array_keys($this->data['elements']['siteTrackFields'][$elementId][$field] ?? self::SITE_IDS_ANY);
            }
        }

        return $sites;
    }

    private function addTrackedField(int $elementId, string $fieldInstanceUid, ?int $siteId): void
    {
        if (isset($this->data['elements']['trackFields'][$elementId][$fieldInstanceUid]) && !isset($this->data['elements']['siteTrackFields'][$elementId][$fieldInstanceUid])) {
            $this->data['elements']['siteTrackFields'][$elementId][$fieldInstanceUid][BaseDataModel::SITE_ID_ANY] = true;
        }
        $this->data['elements']['trackFields'][$elementId][$fieldInstanceUid] = true;
        $this->data['elements']['siteTrackFields'][$elementId][$fieldInstanceUid][$siteId ?? BaseDataModel::SITE_ID_ANY] = true;
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
    private function elementQueriesWithHigherLimitExist(array $params, array $otherElementQueriesParams): bool
    {
        if (!isset($params['limit'])) {
            return false;
        }

        foreach ($otherElementQueriesParams as $otherParams) {
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
