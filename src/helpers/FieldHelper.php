<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\helpers;

use Craft;
use craft\base\ElementInterface;
use craft\base\FieldInterface;
use craft\elements\db\ElementQuery;
use craft\models\FieldLayout;

class FieldHelper
{
    /**
     * Returns the field instance UID of the provided element with the handle.
     */
    public static function getFieldInstanceUidForElement(ElementInterface $element, string $handle): ?string
    {
        $fieldLayout = $element->getFieldLayout();

        if ($fieldLayout === null) {
            return null;
        }

        return self::getFieldInstanceUidForFieldLayout($fieldLayout, $handle);
    }

    /**
     * Returns the field instance UIDs of the provided element with the handles.
     *
     * @param string[] $handles
     * @return string[]
     */
    public static function getFieldInstanceUidsForElement(ElementInterface $element, array $handles): array
    {
        $fieldLayout = $element->getFieldLayout();

        if ($fieldLayout === null) {
            return [];
        }

        $fieldInstanceUids = [];

        foreach ($handles as $handle) {
            $fieldInstanceUid = self::getFieldInstanceUidForFieldLayout($fieldLayout, $handle);

            if ($fieldInstanceUid !== null) {
                $fieldInstanceUids[] = $fieldInstanceUid;
            }
        }

        return $fieldInstanceUids;
    }

    /**
     * Returns the field instance UIDs of the provided element query with the handles.
     *
     * @param string[] $handles
     * @return string[]
     */
    public static function getFieldInstanceUidsForElementQuery(ElementQuery $elementQuery, array $handles): array
    {
        $fieldInstanceUids = [];
        $fieldLayouts = ElementQueryHelper::getElementQueryFieldLayouts($elementQuery);

        foreach ($fieldLayouts as $fieldLayout) {
            foreach ($handles as $handle) {
                $fieldInstanceUid = self::getFieldInstanceUidForFieldLayout($fieldLayout, $handle);

                if ($fieldInstanceUid !== null) {
                    $fieldInstanceUids[] = $fieldInstanceUid;
                }
            }
        }

        return $fieldInstanceUids;
    }

    /**
     * Returns the field instances of the provided UIDs.
     *
     * @param string[] $fieldInstanceUids
     * @return FieldInterface[]
     */
    public static function getFieldInstancesFromUids(array $fieldInstanceUids): array
    {
        $fields = [];
        $fieldLayouts = Craft::$app->getFields()->getAllLayouts();

        foreach ($fieldLayouts as $fieldLayout) {
            foreach ($fieldLayout->getCustomFields() as $field) {
                $layoutElement = $field->layoutElement;
                if ($layoutElement !== null && in_array($layoutElement->uid, $fieldInstanceUids)) {
                    $fields[] = $field;
                }
            }
        }

        return $fields;
    }

    /**
     * Returns the field instance UID of the provided field layout with the handle.
     */
    private static function getFieldInstanceUidForFieldLayout(FieldLayout $fieldLayout, string $handle): ?string
    {
        $field = $fieldLayout->getFieldByHandle($handle);

        if ($field === null) {
            return null;
        }

        $layoutElement = $field->layoutElement;

        if ($layoutElement === null) {
            return null;
        }

        return $layoutElement->uid;
    }
}
