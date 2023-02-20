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
 * This class is a replacement of [[CustomFieldBehavior]] that routes requests
 * through its properties and methods, allowing this class’s magic getter method
 * to register when custom fields are accessed.
 */
class BlitzCustomFieldBehavior extends Behavior
{
    /**
     * @var CustomFieldBehavior
     */
    public CustomFieldBehavior $customFields;

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

        return $this->customFields->$name;
    }

    /**
     * @inerhitdoc
     */
    public function __call($name, $params)
    {
        return $this->customFields->$name($params);
    }

    /**
     * @inheritdoc
     */
    public function __isset($name): bool
    {
        return isset($this->customFields->$name);
    }

    /**
     * @inerhitdoc
     */
    public function __set($name, $value)
    {
        $this->customFields->$name = $value;
    }
}
