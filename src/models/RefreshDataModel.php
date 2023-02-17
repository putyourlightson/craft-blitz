<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\models;

use craft\base\ElementInterface;
use putyourlightson\blitz\helpers\ElementTypeHelper;

/**
 * @inerhitdoc
 *
 * @property-read int[] $cacheIds
 * @property-read array $elementTypes
 * @property-read array|bool $combinedChangedByFields
 */
class RefreshDataModel extends BaseDataModel
{
    /**
     * @var array{
     *          cacheIds: array<int, int[]|bool>,
     *          elements: array<string, array{
     *              sourceIds: array<int, int[]|bool>,
     *              elementIds: array<int, int[]|bool>,
     *              changedByAttributes: array<int, string[]>,
     *              changedByFields: array<int, int[]|bool>,
     *          }>
     *      }
     */
    public array $data = [
        'cacheIds' => [],
        'elements' => [],
    ];

    public static function createFromData(array $data): self
    {
        return new self(['data' => $data]);
    }

    public static function createFromElement(ElementInterface $element): self
    {
        $refreshData = new self();
        $refreshData->addElement($element);

        return $refreshData;
    }

    /**
     * @return int[]
     */
    public function getCacheIds(): array
    {
        return $this->getKeysAsValues(['cacheIds']);
    }

    /**
     * @return array
     */
    public function getElementTypes(): array
    {
        return $this->getKeysAsValues(['elements']);
    }

    /**
     * @return int[]
     */
    public function getSourceIds(string $elementType): array
    {
        return $this->getKeysAsValues(['elements', $elementType, 'sourceIds']);
    }

    /**
     * @return int[]
     */
    public function getElementIds(string $elementType): array
    {
        return $this->getKeysAsValues(['elements', $elementType, 'elementIds']);
    }

    /**
     * @return string[]
     */
    public function getChangedByAttributes(string $elementType, int $elementId): array
    {
        return $this->getKeysAsValues(['elements', $elementType, 'changedByAttributes', $elementId]);
    }

    /**
     * @return int[]|bool|null
     */
    public function getChangedByFields(string $elementType, int $elementId): array|bool|null
    {
        $changedByFields = $this->data['elements'][$elementType]['changedByFields'][$elementId] ?? null;

        if (!is_array($changedByFields)) {
            return $changedByFields;
        }

        return $this->getKeysAsValues(['elements', $elementType, 'changedByFields', $elementId]);
    }

    /**
     * @return int[]|bool|null
     */
    public function getCombinedChangedByFields(string $elementType): array|bool|null
    {
        $fieldIds = [];
        $changedByFields = $this->data['elements'][$elementType]['changedByFields'] ?? [];

        foreach ($changedByFields as $fields) {
            if (empty($fields)) {
                return [];
            } elseif ($fields === true) {
                $fieldIds = true;
            } elseif (is_array($fields)) {
                $fieldIds = array_keys($changedByFields);
            }
        }

        return $fieldIds;
    }

    public function addCacheId(int $cacheId): void
    {
        $this->data['cacheIds'][$cacheId] = true;
    }

    public function addCacheIds(array $cacheIds): void
    {
        foreach ($cacheIds as $cacheId) {
            $this->addCacheId($cacheId);
        }
    }

    public function addSourceId(string $elementType, int $sourceId): void
    {
        $this->data['elements'][$elementType]['sourceIds'][$sourceId] = true;
    }

    public function addElementId(string $elementType, int $elementId): void
    {
        $this->data['elements'][$elementType]['elementIds'][$elementId] = true;
    }

    public function addElementIds(string $elementType, array $elementIds): void
    {
        foreach ($elementIds as $elementId) {
            $this->addElementId($elementType, $elementId);
        }
    }

    public function addElement(ElementInterface $element): void
    {
        $this->addElementId($element::class, $element->id);

        $sourceIdAttribute = ElementTypeHelper::getSourceIdAttribute($element::class);
        if ($sourceIdAttribute !== null) {
            $this->addSourceId($element::class, $element->{$sourceIdAttribute});
        }
    }

    public function addElements(array $elements): void
    {
        foreach ($elements as $element) {
            $this->addElement($element);
        }
    }

    public function addChangedByAttributes(ElementInterface $element, array $changedByAttributes): void
    {
        foreach ($changedByAttributes as $attribute) {
            $this->data['elements'][$element::class]['changedByAttributes'][$element->id][$attribute] = true;
        }
    }

    public function addChangedByFields(ElementInterface $element, array|bool $changedByFields): void
    {
        if (is_array($changedByFields)) {
            foreach ($changedByFields as $fieldId) {
                $this->data['elements'][$element::class]['changedByFields'][$element->id][$fieldId] = true;
            }
        } else {
            $this->data['elements'][$element::class]['changedByFields'][$element->id] = true;
        }
    }
}
