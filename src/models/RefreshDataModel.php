<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\models;

use craft\base\ElementInterface;
use craft\elements\Asset;
use putyourlightson\blitz\behaviors\ElementChangedBehavior;
use putyourlightson\blitz\helpers\ElementTypeHelper;
use putyourlightson\blitz\helpers\FieldHelper;

/**
 * @inerhitdoc
 *
 * @property-read int[] $cacheIds
 * @property-read array $elementTypes
 * @property-read int[] $assetsChangedByImage
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
     *              changedFields: array<int, array<int, bool>>,
     *              isChangedByAttributes: array<int, bool>,
     *              isChangedByFields: array<int, bool>,
     *              isChangedByAssetImage: array<int, bool>,
     *          }>,
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
        return $this->_getCombinedChanged($elementType, 'changedAttributes');
    }

    public function getIsChangedByAttributes(string $elementType, int $elementId): bool
    {
        return $this->data['elements'][$elementType]['isChangedByAttributes'][$elementId] ?? false;
    }

    public function getCombinedIsChangedByAttributes(string $elementType): bool
    {
        return $this->_getCombinedIsChangedBy($elementType, 'isChangedByAttributes');
    }

    /**
     * @return int[]
     */
    public function getChangedFields(string $elementType, int $elementId): array
    {
        $fieldHandles = $this->getKeysAsValues(['elements', $elementType, 'changedFields', $elementId]);

        return FieldHelper::getFieldIdsFromHandles($fieldHandles);
    }

    /**
     * @return int[]
     */
    public function getCombinedChangedFields(string $elementType): array
    {
        $fieldHandles = $this->_getCombinedChanged($elementType, 'changedFields');

        return FieldHelper::getFieldIdsFromHandles($fieldHandles);
    }

    public function getIsChangedByFields(string $elementType, int $elementId): bool
    {
        return $this->data['elements'][$elementType]['isChangedByFields'][$elementId] ?? false;
    }

    public function getCombinedIsChangedByFields(string $elementType): bool
    {
        return $this->_getCombinedIsChangedBy($elementType, 'isChangedByFields');
    }

    /**
     * @return int[]
     */
    public function getAssetsChangedByImage(): array
    {
        return $this->getKeysAsValues(['elements', Asset::class, 'isChangedByAssetImage']);
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

    public function addElement(ElementInterface $element, ?ElementChangedBehavior $elementChanged = null): void
    {
        $this->addElementId($element::class, $element->id);

        $sourceIdAttribute = ElementTypeHelper::getSourceIdAttribute($element::class);
        if ($sourceIdAttribute !== null) {
            $this->addSourceId($element::class, $element->{$sourceIdAttribute});
        }

        if ($elementChanged !== null) {
            $this->addChangedAttributes($element, $elementChanged->changedAttributes);
            $this->addChangedFields($element, $elementChanged->changedFields);
            $this->addIsChangedByAttributes($element, $elementChanged->isChangedByAttributes);
            $this->addIsChangedByFields($element, $elementChanged->isChangedByFields);
            $this->addIsChangedByAssetImage($element, $elementChanged->isChangedByAssetImage);
        }
    }

    public function addElements(array $elements): void
    {
        foreach ($elements as $element) {
            $this->addElement($element);
        }
    }

    public function addChangedAttribute(ElementInterface $element, string $attribute): void
    {
        $this->data['elements'][$element::class]['changedAttributes'][$element->id][$attribute] = true;
    }

    public function addChangedAttributes(ElementInterface $element, array $attributes): void
    {
        foreach ($attributes as $attribute) {
            $this->addChangedAttribute($element, $attribute);
        }
    }

    public function addIsChangedByAttributes(ElementInterface $element, bool $isChangedByAttributes): void
    {
        $previousValue = $this->data['elements'][$element::class]['isChangedByAttributes'][$element->id] ?? true;

        $this->data['elements'][$element::class]['isChangedByAttributes'][$element->id] = $previousValue && $isChangedByAttributes;
    }

    public function addChangedField(ElementInterface $element, string $field): void
    {
        $this->data['elements'][$element::class]['changedFields'][$element->id][$field] = true;
    }

    public function addChangedFields(ElementInterface $element, array $fields): void
    {
        foreach ($fields as $field) {
            $this->addChangedField($element, $field);
        }
    }

    public function addIsChangedByFields(ElementInterface $element, bool $isChangedByFields): void
    {
        $previousValue = $this->data['elements'][$element::class]['isChangedByFields'][$element->id] ?? true;

        $this->data['elements'][$element::class]['isChangedByFields'][$element->id] = $previousValue && $isChangedByFields;
    }

    public function addIsChangedByAssetImage(ElementInterface $element, bool $isChangedByAssetImage): void
    {
        if ($isChangedByAssetImage === true) {
            $this->data['elements'][$element::class]['isChangedByAssetImage'][$element->id] = true;
        }
    }

    private function _getCombinedChanged(string $elementType, string $key): array
    {
        $combined = [];
        $valuesArray = $this->data['elements'][$elementType][$key] ?? [];

        foreach ($valuesArray as $valueArray) {
            foreach ($valueArray as $value => $bool) {
                /** @var string $value */
                $combined[$value] = true;
            }
        }

        return array_keys($combined);
    }

    private function _getCombinedIsChangedBy(string $elementType, string $key): bool
    {
        $values = $this->data['elements'][$elementType][$key] ?? [];

        foreach ($values as $value) {
            if ($value === true) {
                return true;
            }
        }

        return false;
    }
}
