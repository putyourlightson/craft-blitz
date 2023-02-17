<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\models;

/**
 * @inerhitdoc
 *
 * @property-read array $elementIds
 * @property-read array $elementQueryIds
 * @property-read array $ssiIncludeIds
 * @property-read array $elementTrackFields
 */
class GenerateDataModel extends BaseDataModel
{
    /**
     * @var array{
     *          elementIds: array<int, bool>,
     *          elementQueryIds: array<int, bool>,
     *          ssiIncludeIds: array<int, bool>,
     *          elementTrackFields: array<int, int[]|bool>,
     *      }
     */
    public array $data = [
        'elementIds' => [],
        'elementQueryIds' => [],
        'ssiIncludeIds' => [],
        'elementTrackFields' => [],
    ];

    public function getElementIds(): array
    {
        return $this->getKeysAsValues(['elementIds']);
    }

    public function getElementQueryIds(): array
    {
        return $this->getKeysAsValues(['elementQueryIds']);
    }

    public function getSsiIncludeIds(): array
    {
        return $this->getKeysAsValues(['ssiIncludeIds']);
    }

    public function getElementTrackFields(): array
    {
        return $this->data['elementTrackFields'];
    }

    public function addElementId(int $elementId): void
    {
        $this->data['elementIds'][$elementId] = true;
    }

    public function addElementQueryId(int $elementQuery): void
    {
        $this->data['elementQueryIds'][$elementQuery] = true;
    }

    public function addSsiIncludes(int $ssiIncludeId): void
    {
        $this->data['ssiIncludeIds'][$ssiIncludeId] = true;
    }

    public function addElementTrackFields($elementId, $fieldIds): void
    {
        $this->data['elementTrackFields'][$elementId] = $fieldIds;
    }
}
