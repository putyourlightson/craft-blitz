<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\models;

use craft\base\ElementInterface;

/**
 * @inerhitdoc
 *
 * @property-read int[] $elementIds
 * @property-read int[][]|bool[] $elementTrackFields
 * @property-read bool[] $elementTrackAllFields
 * @property-read int[][] $elementTrackSpecificFields
 * @property-read int[] $elementQueryIds
 * @property-read int[] $ssiIncludeIds
 */
class GenerateDataModel extends BaseDataModel
{
    /**
     * @var array{
     *          elements: array{
     *              elementIds: array<int, bool>,
     *              trackFields: array<int, array<int, bool>|bool>,
     *          },
     *          elementQueryIds: array<int, bool>,
     *          ssiIncludeIds: array<int, bool>,
     *      }
     */
    public array $data = [
        'elements' => [
            'elementIds' => [],
            'trackFields' => [],
        ],
        'elementQueryIds' => [],
        'ssiIncludeIds' => [],
    ];

    /**
     * @return int[]
     */
    public function getElementIds(): array
    {
        return $this->getKeysAsValues(['elements', 'elementIds']);
    }

    /**
     * @return int[][]|bool[]
     */
    public function getElementTrackFields(): array
    {
        return $this->data['elements']['trackFields'];
    }

    /**
     * @return bool[]
     */
    public function getElementTrackAllFields(): array
    {
        $trackFields = [];

        foreach ($this->getElementTrackFields() as $elementId => $fields) {
            if ($fields === true) {
                $trackFields[$elementId] = true;
            } else {
                $trackFields[$elementId] = false;
            }
        }

        return $trackFields;
    }

    /**
     * @return int[][]
     */
    public function getElementTrackSpecificFields(): array
    {
        $trackFields = [];

        foreach ($this->getElementTrackFields() as $elementId => $fields) {
            if (is_array($fields)) {
                $trackFields[$elementId] = $fields;
            } else {
                $trackFields[$elementId] = [];
            }
        }

        return $trackFields;
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

    public function addElementId(int $elementId): void
    {
        $this->data['elements']['elementIds'][$elementId] = true;
    }

    public function addElement(ElementInterface $element): void
    {
        $this->addElementId($element->id);
    }

    public function addElementTrackFields(ElementInterface $element, array|bool $fields): void
    {
        $this->data['elements']['trackFields'][$element->id] = $fields;
    }

    public function addElementQueryId(int $elementQuery): void
    {
        $this->data['elementQueryIds'][$elementQuery] = true;
    }

    public function addSsiIncludes(int $ssiIncludeId): void
    {
        $this->data['ssiIncludeIds'][$ssiIncludeId] = true;
    }
}
