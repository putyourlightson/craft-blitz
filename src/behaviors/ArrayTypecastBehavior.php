<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\behaviors;

use yii\base\Behavior;
use yii\base\Model;

/**
 * Typecasts a value to an array.
 *
 * @since 4.0.0
 */
class ArrayTypecastBehavior extends Behavior
{
    /**
     * @var string[] The attributes to typecast.
     */
    public array $attributes;

    /**
     * @inheritdoc
     */
    public function events(): array
    {
        return [Model::EVENT_BEFORE_VALIDATE => 'typecastAttributes'];
    }

    /**
     * Typecast owner attributes according to [[attributes]].
     */
    public function typecastAttributes()
    {
        foreach ($this->attributes as $attribute) {
            $this->owner->{$attribute} = $this->typecastValue($this->owner->{$attribute});
        }
    }

    /**
     * Typecasts a value to an array.
     */
    public function typecastValue($value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (empty($value)) {
            return [];
        }

        return (array)$value;
    }
}
