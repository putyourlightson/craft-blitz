<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\helpers;

use Craft;
use craft\base\ElementInterface;
use craft\behaviors\CustomFieldBehavior;
use craft\db\Query;
use craft\db\Table;
use craft\elements\db\AssetQuery;
use craft\elements\db\ElementQuery;
use craft\elements\db\ElementQueryInterface;
use craft\elements\db\EntryQuery;
use craft\elements\db\OrderByPlaceholderExpression;
use craft\fields\BaseRelationField;
use craft\fields\data\MultiOptionsFieldData;
use craft\fields\data\OptionData;
use craft\helpers\ArrayHelper;
use craft\helpers\Db;
use craft\models\FieldLayout;
use craft\models\Section;
use DateTime;
use putyourlightson\blitz\behaviors\CloneBehavior;
use putyourlightson\blitz\Blitz;
use ReflectionClass;
use ReflectionProperty;
use yii\db\ExpressionInterface;

class ElementQueryHelper
{
    /**
     * @var string[][]
     */
    public static array $filterableElementQueryParams = [];

    /**
     * @var array
     */
    private static array $defaultElementQueryParams = [];

    /**
     * Returns the element query's unique parameters.
     */
    public static function getUniqueElementQueryParams(ElementQuery $elementQuery): array
    {
        $params = [];
        $defaultValues = self::getDefaultElementQueryValues($elementQuery->elementType);

        foreach ($defaultValues as $key => $default) {
            // Ensure the property exists and has not been unset
            // https://github.com/putyourlightson/craft-blitz/issues/471
            if (isset($elementQuery->{$key})) {
                $value = $elementQuery->{$key};

                if ($value !== $default) {
                    $params[$key] = $value;
                }
            }
        }

        // Add the `limit` and `offset` params if they are set on the element query.
        foreach (['limit', 'offset'] as $key) {
            if (!empty($elementQuery->{$key})) {
                $params[$key] = $elementQuery->{$key};

                // Add the `order` param if not empty.
                if (!self::hasEmptyOrderByValue($elementQuery)) {
                    $params['orderBy'] = $elementQuery->orderBy;
                }
            }
        }

        // Exclude specific empty params, as they are redundant.
        foreach (['structureId', 'orderBy'] as $key) {
            // Use `array_key_exists` rather than `isset` as it will return `true` for null results
            if (array_key_exists($key, $params) && empty($params[$key])) {
                unset($params[$key]);
            }
        }

        // Exclude the `query` and `subquery` params, in case they are set.
        // https://github.com/putyourlightson/craft-blitz/issues/579
        foreach (['query', 'subquery'] as $key) {
            if (array_key_exists($key, $params)) {
                unset($params[$key]);
            }
        }

        // Exclude any params specified in the config setting.
        foreach (Blitz::$plugin->settings->excludedTrackedElementQueryParams as $key) {
            if (array_key_exists($key, $params)) {
                unset($params[$key]);
            }
        }

        // Convert ID parameters to arrays
        foreach ($params as $key => $value) {
            if ($key === 'id' || str_ends_with($key, 'Id')) {
                $params[$key] = self::getNormalizedElementQueryIdParam($value);
            }
        }

        // Convert the query parameter values recursively
        array_walk_recursive($params, [__CLASS__, 'convertQueryParamsRecursively']);

        return $params;
    }

    /**
     * Returns the attributes that the element query is filtered or ordered by.
     *
     * @return string[]
     * @see ElementQuery::criteriaAttributes()
     */
    public static function getElementQueryAttributes(ElementQuery $elementQuery): array
    {
        $params = self::getFilterableElementQueryParams($elementQuery);
        $attributes = self::getUsedElementQueryParams($elementQuery, $params);

        // Manually add the default order by if no order is set
        if (self::hasEmptyOrderByValue($elementQuery)) {
            $attributes[] = self::getDefaultOrderByValue($elementQuery);
        }

        // Add `postDate` attribute if either `before` or `after` params exist
        if (!empty($elementQuery->before) || !empty($elementQuery->after)) {
            // Only add if it is a potential param
            if (in_array('postDate', $params)) {
                $attributes[] = 'postDate';
            }
        }

        return array_unique($attributes);
    }

    /**
     * Returns the field instance UIDs that the element query is filtered or ordered by.
     *
     * @return string[]
     */
    public static function getElementQueryFieldInstanceUids(ElementQuery $elementQuery): array
    {
        $fieldHandles = CustomFieldBehavior::$fieldHandles;
        $fieldHandles = self::getUsedElementQueryParams($elementQuery, $fieldHandles);

        return FieldHelper::getFieldInstanceUidsForElementQuery($elementQuery, $fieldHandles);
    }

    /**
     * Returns the field layouts for the element query.
     *
     * @param ElementQuery $elementQuery
     * @return FieldLayout[]
     */
    public static function getElementQueryFieldLayouts(ElementQuery $elementQuery): array
    {
        $fieldLayouts = [];

        if ($elementQuery instanceof EntryQuery) {
            $entryTypeIds = self::normalizeEntryTypeId($elementQuery->typeId);
            if (!empty($entryTypeIds)) {
                foreach ($entryTypeIds as $entryTypeId) {
                    $entryType = Craft::$app->getEntries()->getEntryTypeById($entryTypeId);
                    if ($entryType !== null) {
                        $fieldLayouts[] = $entryType->getFieldLayout();
                    }
                }
            } else {
                $sectionIds = self::normalizeSectionId($elementQuery->sectionId);
                if (!empty($sectionIds)) {
                    foreach ($sectionIds as $sectionId) {
                        $section = Craft::$app->getEntries()->getSectionById($sectionId);
                        if ($section !== null) {
                            foreach ($section->getEntryTypes() as $entryType) {
                                $fieldLayouts[] = $entryType->getFieldLayout();
                            }
                        }
                    }
                }
            }
        }

        if (empty($fieldLayouts)) {
            $fieldLayouts = Craft::$app->getFields()->getLayoutsByType($elementQuery->elementType);
        }

        return $fieldLayouts;
    }

    /**
     * Returns an element query's default values.
     */
    public static function getDefaultElementQueryValues(string $elementType = null): array
    {
        if ($elementType === null) {
            return [];
        }

        if (empty(self::$defaultElementQueryParams[$elementType])) {
            /** @var ElementInterface|string $elementType */
            $elementQuery = $elementType::find();

            $ignoreKeys = [
                'select',
                'with',
                'withStructure',
                'descendantDist',
            ];

            $keys = array_diff($elementQuery->criteriaAttributes(), $ignoreKeys);

            $values = [];
            foreach ($keys as $key) {
                $values[$key] = $elementQuery->{$key};
            }

            self::$defaultElementQueryParams[$elementType] = $values;
        }

        return self::$defaultElementQueryParams[$elementType];
    }

    /**
     * Returns a normalized element query ID parameter.
     */
    public static function getNormalizedElementQueryIdParam(mixed $value): mixed
    {
        if ($value === null || is_int($value)) {
            return $value;
        }

        /**
         * Copied from `ArrayHelper`
         *
         * @see ArrayHelper::toArray()
         */
        if (is_string($value)) {
            // Split it on the non-escaped commas
            $value = preg_split('/(?<!\\\),/', $value);

            // Remove any of the backslashes used to escape the commas
            foreach ($value as $key => $val) {
                // Remove leading/trailing whitespace
                $val = trim($val);

                // Remove any backslashes used to escape commas
                $val = str_replace('\,', ',', $val);

                $value[$key] = $val;
            }

            // Remove any empty elements and reset the keys
            $value = array_values(ArrayHelper::filterEmptyStringsFromArray($value));
        }

        if (is_array($value)) {
            // Convert numeric strings to integers
            foreach ($value as $key => $val) {
                if (is_string($val) && is_numeric($val)) {
                    $value[$key] = (int)$val;
                }
            }

            // If there is only a single value in the array then set the value to it
            if (count($value) === 1) {
                $value = reset($value);
            }
        }

        return $value;
    }

    /**
     * Returns whether the element query has numeric IDs that may be related element IDs.
     */
    public static function hasRelatedElementIds(ElementQuery $elementQuery): bool
    {
        if (!is_array($elementQuery->id)) {
            return false;
        }

        return ArrayHelper::isNumeric($elementQuery->id);
    }

    /**
     * Returns whether the element query has fixed IDs.
     */
    public static function hasFixedIdsOrSlugs(ElementQuery $elementQuery): bool
    {
        // The query values to check
        $values = [
            $elementQuery->id,
            $elementQuery->uid,
            $elementQuery->slug,
            $elementQuery->where['elements.id'] ?? null,
            $elementQuery->where['elements.uid'] ?? null,
            $elementQuery->where['elements.slug'] ?? null,
        ];

        foreach ($values as $value) {
            if (empty($value)) {
                continue;
            }

            if (is_array($value)) {
                $value = $value[0] ?? null;

                if ($value === null) {
                    return true;
                }
            }

            if (is_numeric($value)) {
                return true;
            }

            if (is_string($value) && stripos($value, 'not') !== 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns whether the element query contains an expression on any of its criteria.
     */
    public static function containsExpressionCriteria(ElementQuery $elementQuery): bool
    {
        foreach ($elementQuery->getCriteria() as $criteria) {
            if ($criteria instanceof ExpressionInterface) {
                return true;
            }

            if (is_array($criteria)) {
                foreach ($criteria as $criterion) {
                    if ($criterion instanceof ExpressionInterface) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Creates and returns a clone of the provided element query.
     */
    public static function clone(ElementQuery $elementQuery): ElementQuery
    {
        $elementQueryClone = clone $elementQuery;
        $elementQueryClone->attachBehavior(CloneBehavior::class, CloneBehavior::class);

        return $elementQueryClone;
    }

    /**
     * Returns whether the element query is a clone.
     */
    public static function isClone(ElementQuery $elementQuery): bool
    {
        return $elementQuery->getBehavior(CloneBehavior::class) !== null;
    }

    /**
     * Returns whether the element query is randomly ordered.
     */
    public static function isOrderByRandom(ElementQuery $elementQuery): bool
    {
        /** @phpstan-ignore-next-line */
        if (empty($elementQuery->orderBy) || !is_array($elementQuery->orderBy)) {
            return false;
        }

        $key = key($elementQuery->orderBy);

        if (!is_string($key)) {
            return false;
        }

        $hasMatch = preg_match('/RAND\(.*?\)/i', $key);

        return (bool)$hasMatch;
    }

    /**
     * Returns whether the element query is a draft or revision query.
     */
    public static function isDraftOrRevisionQuery(ElementQuery $elementQuery): bool
    {
        if ($elementQuery->drafts || $elementQuery->revisions) {
            return true;
        }

        return false;
    }

    /**
     * Returns whether the element query is an entry query for “single” sections.
     *
     * @since 4.11.0
     */
    public static function isEntryQueryForSingleSections(ElementQuery $elementQuery): bool
    {
        if (!($elementQuery instanceof EntryQuery)) {
            return false;
        }

        $sectionIds = $elementQuery->sectionId;

        if (!is_array($sectionIds)) {
            $sectionIds = [$sectionIds];
        }

        $singles = Craft::$app->getEntries()->getSectionsByType(Section::TYPE_SINGLE);
        $singlesIds = ArrayHelper::getColumn($singles, 'id');

        foreach ($sectionIds as $sectionId) {
            if (!is_numeric($sectionId) || !in_array($sectionId, $singlesIds)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Returns whether the element query is an asset query with a filename.
     *
     * @since 4.11.2
     */
    public static function isAssetQueryWithFilename(ElementQuery $elementQuery): bool
    {
        if (!($elementQuery instanceof AssetQuery)) {
            return false;
        }

        return $elementQuery->filename !== null;
    }

    /**
     * Returns whether the element query is a nested entry query.
     */
    public static function isNestedEntryQuery(ElementQuery $elementQuery): bool
    {
        if (!($elementQuery instanceof EntryQuery)) {
            return false;
        }

        /**
         * Evaluate whether the element query has an owner.
         *
         * @see EntryQuery::beforePrepare()
         */
        return !empty($elementQuery->fieldId)
            || !empty($elementQuery->ownerId)
            || !empty($elementQuery->primaryOwnerId);
    }

    /**
     * Returns whether the element query is a relation field query.
     *
     * For example:
     *
     * ```twig
     * {% set relatedEntries = entry.relatedEntries.all() %}
     * ```
     */
    public static function isRelationFieldQuery(ElementQuery $elementQuery): bool
    {
        return $elementQuery->getBehavior(BaseRelationField::class) !== null;
    }

    /**
     * Returns an element query’s filterable params, which is the intersection
     * of its params and its element type’s params.
     */
    private static function getFilterableElementQueryParams(ElementQuery $elementQuery): array
    {
        if (empty(self::$filterableElementQueryParams[$elementQuery::class])) {
            $elementQueryParams = [];
            foreach (self::getPublicNonStaticProperties($elementQuery::class) as $property) {
                $elementQueryParams[] = $property;
            }

            $elementTypeParams = [];
            foreach (self::getPublicNonStaticProperties($elementQuery->elementType) as $property) {
                $elementTypeParams[] = $property;
            }

            // Ignore params that never change. The `orderBy` attribute is extracted separately later.
            $sourceIdAttribute = ElementTypeHelper::getSourceIdAttribute($elementQuery->elementType);
            $ignoreParams = [
                $sourceIdAttribute,
                'id',
                'uid',
                'siteId',
                'structureId',
                'level',
                'orderBy',
            ];

            self::$filterableElementQueryParams[$elementQuery::class] = array_diff(
                array_intersect(
                    $elementQueryParams,
                    $elementTypeParams,
                ),
                $ignoreParams,
            );
        }

        return self::$filterableElementQueryParams[$elementQuery::class];
    }

    /**
     * Returns a class’ public, non-static properties using reflection.
     *
     * @see ElementQuery::criteriaAttributes()
     */
    private static function getPublicNonStaticProperties(string $class): array
    {
        $properties = [];

        $publicProperties = (new ReflectionClass($class))->getProperties(ReflectionProperty::IS_PUBLIC);
        foreach ($publicProperties as $property) {
            if (!$property->isStatic()) {
                $properties[] = $property->getName();
            }
        }

        return $properties;
    }

    /**
     * Returns whether the order by value has an empty or placeholder value.
     */
    private static function hasEmptyOrderByValue(ElementQuery $elementQuery): bool
    {
        $orderBy = $elementQuery->orderBy[0] ?? $elementQuery->orderBy;

        return $orderBy === null || $orderBy instanceof OrderByPlaceholderExpression;
    }

    /**
     * Returns the default order by value for the element query using reflection.
     */
    private static function getDefaultOrderByValue(ElementQuery $elementQuery): string
    {
        // Use reflection to extract the protected property value
        $property = (new ReflectionClass($elementQuery))->getProperty('defaultOrderBy');
        $defaultOrderBy = $property->getValue($elementQuery);

        if (is_array($defaultOrderBy)) {
            $defaultOrderBy = array_key_first($defaultOrderBy);

            // Get the column name without the table
            $parts = explode('.', $defaultOrderBy);
            $defaultOrderBy = end($parts);
        }

        return $defaultOrderBy;
    }

    /**
     * Returns the params used by the element query.
     */
    private static function getUsedElementQueryParams(ElementQuery $elementQuery, array $allParams): array
    {
        $uniqueParams = self::getUniqueElementQueryParams($elementQuery);
        $params = [];

        foreach ($uniqueParams as $name => $value) {
            if (in_array($name, $allParams)) {
                $params[] = $name;
            }
        }

        $orderBy = $elementQuery->orderBy;
        if (is_array($orderBy)) {
            foreach ($orderBy as $name => $value) {
                // Extract the attribute in case of `table.column` format
                $parts = explode('.', $name);
                $name = end($parts);

                if (in_array($name, $allParams)) {
                    $params[] = $name;
                }
            }
        }

        return array_unique($params);
    }

    /**
     * Converts query parameter values to more concise formats recursively.
     */
    private static function convertQueryParamsRecursively(mixed &$value): void
    {
        // Convert elements to their ID
        if ($value instanceof ElementInterface) {
            $value = $value->getId();
            return;
        }

        // Convert element queries to element IDs
        if ($value instanceof ElementQueryInterface) {
            $value = $value->ids();
            return;
        }

        // Convert DateTime objects to Unix timestamp
        if ($value instanceof DateTime) {
            $value = $value->getTimestamp();
        }

        // Convert OptionData objects to their values (ignoring whether selected).
        if ($value instanceof OptionData) {
            $value = $value->value;
        }

        // Convert MultiOptionsFieldData objects to arrays of values (ignoring whether selected).
        if ($value instanceof MultiOptionsFieldData) {
            $options = $value->getOptions();
            $value = [];
            foreach ($options as $option) {
                $value[] = $option->value;
            }
        }
    }

    /**
     * Normalizes the section ID to an array of IDs or null.
     *
     * @see EntryQuery::_normalizeSectionId()
     */
    private static function normalizeSectionId(mixed $sectionId): ?array
    {
        if (empty($sectionId)) {
            $sectionId = is_array($sectionId) ? [] : null;
        } elseif (is_numeric($sectionId)) {
            $sectionId = [$sectionId];
        } elseif (!is_array($sectionId) || !ArrayHelper::isNumeric($sectionId)) {
            $sectionId = (new Query())
                ->select(['id'])
                ->from([Table::SECTIONS])
                ->where(Db::parseNumericParam('id', $sectionId))
                ->column();
        }

        return $sectionId;
    }

    /**
     * Normalizes the type ID to an array of IDs or null.
     *
     * @see EntryQuery::_normalizeTypeId()
     */
    private static function normalizeEntryTypeId(mixed $typeId): ?array
    {
        if (empty($typeId)) {
            $typeId = is_array($typeId) ? [] : null;
        } elseif (is_numeric($typeId)) {
            $typeId = [$typeId];
        } elseif (!is_array($typeId) || !ArrayHelper::isNumeric($typeId)) {
            $typeId = (new Query())
                ->select(['id'])
                ->from([Table::ENTRYTYPES])
                ->where(Db::parseNumericParam('id', $typeId))
                ->column();
        }

        return $typeId;
    }
}
