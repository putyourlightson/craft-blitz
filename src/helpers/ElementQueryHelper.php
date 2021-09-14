<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\helpers;

use craft\base\ElementInterface;
use craft\elements\db\ElementQuery;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\ArrayHelper;
use DateTime;
use craft\db\FixedOrderExpression;

class ElementQueryHelper
{
    // Properties
    // =========================================================================

    /**
     * @var array
     */
    private static $_defaultElementQueryParams = [];

    // Public Methods
    // =========================================================================

    /**
     * Returns the element query's unique parameters.
     *
     * @param ElementQuery $elementQuery
     *
     * @return array
     */
    public static function getUniqueElementQueryParams(ElementQuery $elementQuery): array
    {
        $params = [];

        $defaultParams = self::getDefaultElementQueryParams($elementQuery->elementType);

        foreach ($defaultParams as $key => $default) {
            $value = $elementQuery->{$key};

            if ($value !== $default) {
                $params[$key] = $value;
            }
        }

        // Ignore specific empty params as they are redundant
        $ignoreEmptyParams = ['structureId', 'orderBy', 'limit', 'offset'];

        foreach ($ignoreEmptyParams as $key) {
            // Use `array_key_exists` rather than `isset` as it will return `true` for null results
            if (array_key_exists($key, $params) && empty($params[$key])) {
                unset($params[$key]);
            }
        }

        // Convert ID parameters to arrays
        foreach ($params as $key => $value) {
            if ($key == 'id' || substr($key, -2) == 'Id') {
                $params[$key] = self::getNormalizedElementQueryIdParam($value);
            }
        }

        // Convert the query parameter values recursively
        array_walk_recursive($params, [__CLASS__, '_convertQueryParamsRecursively']);

        return $params;
    }

    /**
     * Returns an element query's default parameters for a given element type.
     *
     * @param string|null $elementType
     *
     * @return array
     */
    public static function getDefaultElementQueryParams(string $elementType = null): array
    {
        if ($elementType === null) {
            return [];
        }

        if (!empty(self::$_defaultElementQueryParams[$elementType])) {
            return self::$_defaultElementQueryParams[$elementType];
        }

        /** @var ElementInterface|string $elementType */
        self::$_defaultElementQueryParams[$elementType] = get_object_vars($elementType::find());

        $ignoreParams = [
            'select',
            'with',
            'query',
            'subQuery',
            'customFields',
            'withStructure',
            'descendantDist',
        ];

        foreach ($ignoreParams as $key) {
            unset(self::$_defaultElementQueryParams[$elementType][$key]);
        }

        return self::$_defaultElementQueryParams[$elementType];
    }

    /**
     * Returns a normalized element query ID parameter.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    public static function getNormalizedElementQueryIdParam($value)
    {
        if ($value === null || is_int($value)) {
            return $value;
        }

        /**
         * Copied from Db helper
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
     *
     * @param ElementQuery $elementQuery
     *
     * @return bool
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
     * Returns whether the element query is randomly ordered.
     *
     * @param ElementQuery $elementQuery
     *
     * @return bool
     */
    public static function isOrderByRandom(ElementQuery $elementQuery): bool
    {
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
     *
     * @param ElementQuery $elementQuery
     *
     * @return bool
     */
    public static function isDraftOrRevisionQuery(ElementQuery $elementQuery): bool
    {
        if ($elementQuery->drafts || $elementQuery->revisions) {
            return true;
        }

        return false;
    }

    /**
     * Returns whether the element query is a relation query.
     *
     * @param ElementQuery $elementQuery
     *
     * @return bool
     */
    public static function isRelationQuery(ElementQuery $elementQuery): bool
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
     * Returns whether the element query is order by a FixedOrderExpression
     *
     * @param ElementQuery $elementQuery
     *
     * @return bool
     */
    public static function isOrderByFixedOrderExpression(ElementQuery $elementQuery): bool
    {
        $orderBy = $elementQuery->orderBy;

        return $orderBy instanceof FixedOrderExpression;
    }

    // Private Methods
    // =========================================================================

    /**
     * Converts query parameter values to more concise formats recursively.
     *
     * @param mixed $value
     */
    private static function _convertQueryParamsRecursively(&$value)
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
            return;
        }
    }
}
