<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\behaviors;

use Craft;
use craft\base\Element;
use craft\elements\Asset;
use putyourlightson\blitz\helpers\ElementTypeHelper;
use putyourlightson\blitz\helpers\FieldHelper;
use yii\base\Behavior;

/**
 * This behavior detects whether an element has changed.
 *
 * @since 3.6.0
 *
 * @property-read bool $hasChanged
 * @property-read bool $hasBeenDeleted
 * @property-read bool $hasStatusChanged
 * @property-read bool $hasFocalPointChanged
 * @property-read bool $hasRefreshableStatus
 * @property-read bool $haveAttributesChanged
 * @property-read bool $haveFieldsChanged
 * @property-read string[] $changedFields
 * @property Element $owner
 */
class ElementChangedBehavior extends Behavior
{
    /**
     * @const string
     */
    public const BEHAVIOR_NAME = 'elementChanged';

    /**
     * @var Element|null The original element.
     */
    public ?Element $originalElement = null;

    /**
     * @var string[] The attributes that changed.
     */
    public array $changedAttributes = [];

    /**
     * @var int[]|bool The field IDs that changed, or `true` if all fields changed.
     */
    public array|bool $changedFields = [];

    /**
     * @var bool Whether the element was changed by field only.
     */
    public bool $changedByFieldsOnly = false;

    /**
     * @inerhitdoc
     */
    public function attach($owner): void
    {
        parent::attach($owner);

        $element = $this->owner;

        // Don't proceed if this is a new element
        if ($element->id === null) {
            return;
        }

        $this->originalElement = Craft::$app->getElements()->getElementById($element->id, $element::class, $element->siteId);
    }

    /**
     * Returns whether the element has changed.
     */
    public function getHasChanged(): bool
    {
        $element = $this->owner;

        $this->changedAttributes = $this->_getChangedAttributes();
        $this->changedFields = $this->_getChangedFields();

        if ($element->firstSave) {
            return true;
        }

        if ($this->getHasBeenDeleted()) {
            return true;
        }

        if ($this->getHasStatusChanged()) {
            return true;
        }

        if ($this->getHasFocalPointChanged()) {
            return true;
        }

        if (!empty($this->changedAttributes)) {
            return true;
        }

        if (!empty($this->changedFields)) {
            $this->changedByFieldsOnly = true;

            return true;
        }

        return false;
    }

    /**
     * Returns whether the element has been deleted.
     */
    public function getHasBeenDeleted(): bool
    {
        $element = $this->owner;

        return $element->dateDeleted !== null;
    }

    /**
     * Returns whether the element's status has changed.
     */
    public function getHasStatusChanged(): bool
    {
        $element = $this->owner;

        if ($this->originalElement === null) {
            return false;
        }

        return $element->getStatus() != $this->originalElement->getStatus();
    }

    /**
     * Returns whether the element's focal point has changed, if an asset, which takes
     * image cropping and rotation into account too.
     */
    public function getHasFocalPointChanged(): bool
    {
        $element = $this->owner;

        if (!($element instanceof Asset) || !($this->originalElement instanceof Asset)) {
            return false;
        }

        // Comparing floats is problematic, so we convert to a fixed precision first.
        // https://www.php.net/manual/en/language.types.float.php
        $precision = 5;
        $originalFocalPoint = [
            number_format($this->originalElement->focalPoint['x'], $precision),
            number_format($this->originalElement->focalPoint['y'], $precision),
        ];
        $focalPoint = [
            number_format($element->focalPoint['x'], $precision),
            number_format($element->focalPoint['y'], $precision),
        ];

        return $focalPoint != $originalFocalPoint;
    }

    /**
     * Returns whether the element has a live, pending or expired status.
     */
    public function getHasRefreshableStatus(): bool
    {
        $element = $this->owner;
        $elementStatus = $element->getStatus();
        $liveStatus = ElementTypeHelper::getLiveStatus($element::class);
        $refreshableStatuses = [
            $liveStatus,
            'pending',
            'expired',
        ];

        return in_array($elementStatus, $refreshableStatuses);
    }

    /**
     * Returns the attributes that have changed.
     */
    private function _getChangedAttributes(): array
    {
        $element = $this->owner;

        if ($element->duplicateOf === null) {
            $changedAttributes = $element->getDirtyAttributes();
        } else {
            $changedAttributes = $element->duplicateOf->getModifiedAttributes();
        }

        return $changedAttributes;

    }

    /**
     * Returns the IDs of the custom fields that have changed, or `true` if all have.
     *
     * @return int[]|bool
     */
    private function _getChangedFields(): array|bool
    {
        $element = $this->owner;

        if ($element->duplicateOf == null) {
            // Only elements that support drafts can track changed fields:
            // https://github.com/craftcms/cms/discussions/12667
            $changedFieldHandles = $element->getDirtyFields();

            // Check if all custom fields are dirty
            $fieldLayout = $element->getFieldLayout();
            $customFields = $fieldLayout->getCustomFields();
            $customFieldHandles = array_map(fn($field) => $field->handle, $customFields);

            if (empty(array_diff($customFieldHandles, $changedFieldHandles))) {
                return true;
            }
        } else {
            $changedFieldHandles = $element->duplicateOf->getModifiedFields();
        }

        return FieldHelper::getFieldIdsFromHandles($changedFieldHandles);
    }
}
