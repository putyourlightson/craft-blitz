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
     *          elementQueryIds: array<int, bool>,
     *          ssiIncludeIds: array<int, bool>,
     *          hasIncludes: bool,
     *      }
     */
    public array $data = [
        'elements' => [
            'elementIds' => [],
            'trackFields' => [],
        ],
        'elementQueryIds' => [],
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
     * @return int[]
     */
    public function getElementQueryIds(): array
    {
        return $this->getKeysAsValues(['elementQueryIds']);
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
        $this->addElementId($element->id);
    }

    public function addElementTrackField(ElementInterface $element, $field): void
    {
        $this->data['elements']['trackFields'][$element->id][$field] = true;
    }

    public function addElementQueryId(int $elementQuery): void
    {
        $this->data['elementQueryIds'][$elementQuery] = true;
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
}
