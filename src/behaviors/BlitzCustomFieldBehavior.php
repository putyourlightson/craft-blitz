<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\behaviors;

use craft\base\ElementInterface;
use craft\behaviors\CustomFieldBehavior;
use putyourlightson\blitz\Blitz;
use yii\base\Behavior;

/**
 * This class is a replacement of [[CustomFieldBehavior]] (but not a subclass)
 * that routes requests through its methods, allowing this class’s magic getter
 * method to register when custom fields are accessed.
 *
 * @since 4.4.0
 */
class BlitzCustomFieldBehavior extends Behavior
{
    /**
     * @var CustomFieldBehavior The original custom field behavior attached to the element.
     */
    public CustomFieldBehavior $customFields;

    public static function create(CustomFieldBehavior $customFields): self
    {
        return new self(['customFields' => $customFields]);
    }

    /**
     * @inheritdoc
     */
    public function __call($name, $params)
    {
        return $this->customFields->__call($name, $params);
    }

    /**
     * @inheritdoc
     */
    public function hasMethod($name): bool
    {
        return $this->customFields->hasMethod($name);
    }

    /**
     * @inheritdoc
     */
    public function __isset($name): bool
    {
        return $this->customFields->__isset($name);
    }

    /**
     * Adds a field to track on the element, if the property is a custom field.
     */
    public function __get($name)
    {
        if (!empty(CustomFieldBehavior::$fieldHandles[$name])) {
            /** @var ElementInterface $element */
            $element = $this->owner;
            Blitz::$plugin->generateCache->generateData->addElementTrackField($element, $name);
        }

        // Get the property directly rather than going through the magic getter
        return $this->customFields->$name;
    }

    /**
     * @inheritdoc
     */
    public function __set($name, $value)
    {
        // Set the property directly rather than going through the magic setter
        $this->customFields->$name = $value;
    }

    /**
     * @inheritdoc
     */
    public function canGetProperty($name, $checkVars = true): bool
    {
        return $this->customFields->canGetProperty($name, $checkVars);
    }

    /**
     * @inheritdoc
     */
    public function canSetProperty($name, $checkVars = true): bool
    {
        return $this->customFields->canSetProperty($name, $checkVars);
    }
}
