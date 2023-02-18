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
 * @property-read array|bool $combinedChangedFields
 */
class RefreshDataModel extends BaseDataModel
{
    /**
     * @var array{
     *          cacheIds: array<int, int[]|bool>,
     *          elements: array<string, array{
     *              sourceIds: array<int, bool>,
     *              elementIds: array<int, bool>,
     *              changedAttributes: array<int, array<string, bool>>,
     *              changedFields: array<int, array<int, bool>|bool>,
     *              isChangedByAttributes: array<int, bool>,
     *              isChangedByFields: array<int, bool>,
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
    public function getChangedAttributes(string $elementType, int $elementId): array
    {
        return $this->getKeysAsValues(['elements', $elementType, 'changedAttributes', $elementId]);
    }

    /**
     * @return string[]
     */
    public function getCombinedChangedAttributes(string $elementType): array
    {
        $combinedAttributes = [];
        $changedAttributes = $this->data['elements'][$elementType]['changedAttributes'] ?? [];

        foreach ($changedAttributes as $attributes) {
            foreach ($attributes as $attribute => $value) {
                /** @var string $attribute */
                $combinedAttributes[$attribute] = true;
            }
        }

        return array_keys($combinedAttributes);
    }

    public function getIsChangedByAttributes(string $elementType, int $elementId): bool
    {
        return $this->data['elements'][$elementType]['isChangedByAttributes'][$elementId] ?? false;
    }

    public function getCombinedIsChangedByAttributes(string $elementType): bool
    {
        $isChangedByAttributes = $this->data['elements'][$elementType]['isChangedByAttributes'] ?? [];

        foreach ($isChangedByAttributes as $isChangedByAttribute) {
            if ($isChangedByAttribute === true) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return int[]|bool
     */
    public function getChangedFields(string $elementType, int $elementId): array|bool
    {
        $changedFields = $this->data['elements'][$elementType]['changedFields'][$elementId] ?? [];

        if (is_bool($changedFields)) {
            return $changedFields;
        }

        return $this->getKeysAsValues(['elements', $elementType, 'changedFields', $elementId]);
    }

    /**
     * @return int[]|bool|null
     */
    public function getCombinedChangedFields(string $elementType): array|bool|null
    {
        $combinedFields = [];
        $changedFields = $this->data['elements'][$elementType]['changedFields'] ?? [];

        foreach ($changedFields as $fields) {
            if (empty($fields)) {
                return [];
            } elseif ($fields === true) {
                $combinedFields = true;
            } elseif (is_array($fields)) {
                foreach ($fields as $field => $value) {
                    $combinedFields[$field] = true;
                }
            }
        }

        if (is_bool($combinedFields)) {
            return $combinedFields;
        }

        return array_keys($combinedFields);
    }

    public function getIsChangedByFields(string $elementType, int $elementId): bool
    {
        return $this->data['elements'][$elementType]['isChangedByFields'][$elementId] ?? false;
    }

    public function getCombinedIsChangedByFields(string $elementType): bool
    {
        $isChangedByFields = $this->data['elements'][$elementType]['isChangedByFields'] ?? [];

        foreach ($isChangedByFields as $isChangedByField) {
            if ($isChangedByField === true) {
                return true;
            }
        }

        return false;
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

    public function addChangedAttributes(ElementInterface $element, array $changedAttributes): void
    {
        foreach ($changedAttributes as $attribute) {
            $this->data['elements'][$element::class]['changedAttributes'][$element->id][$attribute] = true;
        }
    }

    public function addIsChangedByAttributes(ElementInterface $element, bool $isChangedByAttributes): void
    {
        $previousValue = $this->data['elements'][$element::class]['isChangedByAttributes'][$element->id] ?? true;

        $this->data['elements'][$element::class]['isChangedByAttributes'][$element->id] = $previousValue && $isChangedByAttributes;
    }

    public function addChangedFields(ElementInterface $element, array|bool $changedFields): void
    {
        $previousChangedFields = $this->data['elements'][$element::class]['changedFields'][$element->id] ?? [];

        // If either is `true` for this element, make it `true`.
        if ($previousChangedFields === true || $changedFields === true) {
            $this->data['elements'][$element::class]['changedFields'][$element->id] = true;
            return;
        }

        foreach ($changedFields as $fieldId) {
            $this->data['elements'][$element::class]['changedFields'][$element->id][$fieldId] = true;
        }
    }

    public function addIsChangedByFields(ElementInterface $element, bool $isChangedByFields): void
    {
        $previousValue = $this->data['elements'][$element::class]['isChangedByFields'][$element->id] ?? true;

        $this->data['elements'][$element::class]['isChangedByFields'][$element->id] = $previousValue && $isChangedByFields;
    }
}
