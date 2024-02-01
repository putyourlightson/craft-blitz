<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\helpers;

use craft\base\ElementInterface;
use craft\behaviors\CustomFieldBehavior;
use craft\elements\db\ElementQuery;
use craft\elements\db\ElementQueryInterface;
use craft\fields\data\MultiOptionsFieldData;
use craft\fields\data\OptionData;
use craft\helpers\ArrayHelper;
use DateTime;
use ReflectionClass;
use ReflectionProperty;
use yii\db\Expression;

class ElementQueryHelper
{
    /**
     * @var string[][]
     */
    public static array $_filterableElementQueryParams = [];

    /**
     * @var array
     */
    private static array $_defaultElementQueryParams = [];

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

        // Exclude query and subquery params, in case they are set.
        // https://github.com/putyourlightson/craft-blitz/issues/579
        foreach (['query', 'subquery'] as $key) {
            if (array_key_exists($key, $params)) {
                unset($params[$key]);
            }
        }

        // Ignore specific empty params as they are redundant
        $ignoreEmptyParams = [
            'structureId',
            'orderBy',
            'limit',
            'offset',
        ];

        foreach ($ignoreEmptyParams as $key) {
            // Use `array_key_exists` rather than `isset` as it will return `true` for null results
            if (array_key_exists($key, $params) && empty($params[$key])) {
                unset($params[$key]);
            }
        }

        // Convert ID parameters to arrays
        foreach ($params as $key => $value) {
            if ($key == 'id' || str_ends_with($key, 'Id')) {
                $params[$key] = self::getNormalizedElementQueryIdParam($value);
            }
        }

        // Convert the query parameter values recursively
        array_walk_recursive($params, [__CLASS__, '_convertQueryParamsRecursively']);

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
        $params = self::_getFilterableElementQueryParams($elementQuery);
        $attributes = self::_getUsedElementQueryParams($elementQuery, $params);

        // Manually add the default order by if no order is set
        if (empty($elementQuery->orderBy)) {
            // Use reflection to extract the protected property value
            $property = (new ReflectionClass($elementQuery))->getProperty('defaultOrderBy');
            $property->setAccessible(true);
            $defaultOrderBy = $property->getValue($elementQuery);

            if (!empty($defaultOrderBy)) {
                if (is_array($defaultOrderBy)) {
                    $defaultOrderBy = array_key_first($defaultOrderBy);

                    // Get the column name without the table
                    $parts = explode('.', $defaultOrderBy);
                    $attributes[] = end($parts);
                } else {
                    $attributes[] = $defaultOrderBy;
                }
            }
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
     * Returns the field IDs that the element query is filtered or ordered by.
     *
     * @return int[]
     */
    public static function getElementQueryFieldIds(ElementQuery $elementQuery): array
    {
        $fieldHandles = CustomFieldBehavior::$fieldHandles;
        $fieldHandles = self::_getUsedElementQueryParams($elementQuery, $fieldHandles);

        return FieldHelper::getFieldIdsFromHandles($fieldHandles);
    }

    /**
     * Returns an element query's default values.
     */
    public static function getDefaultElementQueryValues(string $elementType = null): array
    {
        if ($elementType === null) {
            return [];
        }

        if (!empty(self::$_defaultElementQueryParams[$elementType])) {
            return self::$_defaultElementQueryParams[$elementType];
        }

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

        self::$_defaultElementQueryParams[$elementType] = $values;

        return self::$_defaultElementQueryParams[$elementType];
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
         * Copied from Db helper
         *
         * @see \craft\helpers\Db::_toArray
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
            if ($criteria instanceof Expression) {
                return true;
            }

            if (is_array($criteria)) {
                foreach ($criteria as $criterion) {
                    if ($criterion instanceof Expression) {
                        return true;
                    }
                }
            }
        }

        return false;
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
     * Returns whether the element query is a relation field query.
     * For example:
     *
     * ```twig
     * {% set relatedEntries = entry.relatedEntries.all() %}
     * ```
     */
    public static function isRelationFieldQuery(ElementQuery $elementQuery): bool
    {
        if (empty($elementQuery->join)) {
            return false;
        }

        $join = $elementQuery->join[0] ?? null;

        if ($join === null) {
            return false;
        }

        $relationTypes = [
            ['relations' => '{{%relations}}'],
            '{{%relations}} relations',
        ];

        if ($join[0] == 'INNER JOIN' && in_array($join[1], $relationTypes)) {
            return true;
        }

        return false;
    }

    /**
     * Returns an element query’s filterable params, which is the intersection
     * of its params and its element type’s params.
     */
    private static function _getFilterableElementQueryParams(ElementQuery $elementQuery): array
    {
        if (!empty(self::$_filterableElementQueryParams[$elementQuery::class])) {
            return self::$_filterableElementQueryParams[$elementQuery::class];
        }

        $elementQueryParams = [];
        foreach (self::_getPublicNonStaticProperties($elementQuery::class) as $property) {
            $elementQueryParams[] = $property;
        }

        $elementTypeParams = [];
        foreach (self::_getPublicNonStaticProperties($elementQuery->elementType) as $property) {
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

        self::$_filterableElementQueryParams[$elementQuery::class] = array_diff(
            array_intersect(
                $elementQueryParams,
                $elementTypeParams,
            ),
            $ignoreParams,
        );

        return self::$_filterableElementQueryParams[$elementQuery::class];
    }

    /**
     * Returns a class’ public, non-static properties.
     *
     * @see ElementQuery::criteriaAttributes()
     */
    private static function _getPublicNonStaticProperties(string $class): array
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
     * Returns the params used by the element query.
     */
    private static function _getUsedElementQueryParams(ElementQuery $elementQuery, array $allParams): array
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
    private static function _convertQueryParamsRecursively(mixed &$value): void
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
}
