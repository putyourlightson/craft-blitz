<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\validators;

use yii\validators\Validator;

/**
 * Validates that the value is an array, defaulting to a blank array.
 *
 * @since 4.0.0
 */
class ArrayTypeValidator extends Validator
{
    /**
     * @inheritdoc
     */
    public function validateAttribute($model, $attribute)
    {
        if (!is_array($model->{$attribute})) {
            $model->{$attribute} = [];
        }
    }

    /**
     * @inheritdoc
     */
    protected function validateValue($value): ?array
    {
        return null;
    }
}
