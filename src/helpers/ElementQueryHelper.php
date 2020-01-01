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

class ElementQueryHelper
{
    // Properties
    // =========================================================================

    /**
     * @var array
     */
    private static $_defaultParams = [];

    // Static
    // =========================================================================

    /**
     * Returns an element query's default parameters for a given element type.
     *
     * @param string $elementType
     *
     * @return array
     */
    public static function getDefaultParams(string $elementType): array
    {
        if (!empty(self::$_defaultParams[$elementType])) {
            return self::$_defaultParams[$elementType];
        }

        self::$_defaultParams[$elementType] = get_object_vars($elementType::find());

        $ignoreParams = ['select', 'with', 'query', 'subQuery', 'customFields'];

        foreach ($ignoreParams as $key) {
            unset(self::$_defaultParams[$elementType][$key]);
        }

        return self::$_defaultParams[$elementType];
    }

    /**
     * Returns the element query's unique parameters.
     *
     * @param ElementQuery $elementQuery
     *
     * @return array
     */
    public static function getUniqueParams(ElementQuery $elementQuery): array
    {
        $params = [];

        $defaultParams = self::getDefaultParams($elementQuery->elementType);

        foreach ($defaultParams as $key => $default) {
            $value = $elementQuery->{$key};

            if ($value !== $default) {
                $params[$key] = $value;
            }
        }

        // Ignore specific empty params as they are redundant
        $ignoreEmptyParams = ['structureId', 'orderBy'];

        foreach ($ignoreEmptyParams as $key) {
            // Use `array_key_exists` rather than `isset` as it will return `true` for null results
            if (array_key_exists($key, $params) && empty($params[$key])) {
                unset($params[$key]);
            }
        }

        // Convert ID parameters to arrays
        foreach ($params as $key => $value) {
            if ($key == 'id' || substr($key, -2) == 'Id') {
                $params[$key] = self::getNormalizedIdParam($value);
            }
        }

        // Convert the query parameter values recursively
        array_walk_recursive($params, [__CLASS__ , 'convertQueryParamsRecursively']);

        return $params;
    }

    /**
     * Returns a normalized element query ID parameter.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    public static function getNormalizedIdParam($value)
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
    public static function hasFixedIds(ElementQuery $elementQuery): bool
    {
        // The query values to check
        $values = [
            $elementQuery->id,
            $elementQuery->uid,
            $elementQuery->where['elements.id'] ?? null,
            $elementQuery->where['elements.uid'] ?? null,
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
     * Converts query parameter values to more concise formats recursively.
     *
     * @param mixed $value
     */
    public static function convertQueryParamsRecursively(&$value)
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
