<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\helpers;

use Craft;

class FieldHelper
{
    /**
     * Returns the field IDs of the provided field handles.
     *
     * @param string[] $handles
     * @return int[]
     */
    public static function getFieldIdsFromHandles(array $handles): array
    {
        $fieldIds = [];

        foreach ($handles as $handle) {
            $field = Craft::$app->getFields()->getFieldByHandle($handle);

            if ($field !== null) {
                $fieldIds[] = $field->id;
            }
        }

        return $fieldIds;
    }
}
