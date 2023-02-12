<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\behaviors;

use Craft;
use craft\base\Element;
use craft\elements\Asset;
use putyourlightson\blitz\helpers\ElementTypeHelper;
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
     * @var array|null The fields that have changed on the element, only if
     * nothing else has changed.
     */
    public array|null $onlyFieldsChanged = null;

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

        $this->originalElement = Craft::$app->getElements()->getElementById($element->id, get_class($element), $element->siteId);
    }

    /**
     * Returns whether the element has changed.
     */
    public function getHasChanged(): bool
    {
        $element = $this->owner;

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

        if ($this->getHaveAttributesChanged()) {
            return true;
        }

        $changedFields = $this->getChangedFields();
        if (!empty($changedFields)) {
            $this->onlyFieldsChanged = $changedFields;

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
        $liveStatus = ElementTypeHelper::getLiveStatus(get_class($element));
        $refreshableStatuses = [
            $liveStatus,
            'pending',
            'expired',
        ];

        return in_array($elementStatus, $refreshableStatuses);
    }

    /**
     * Returns whether any of the element’s attributes have changed.
     */
    public function getHaveAttributesChanged(): bool
    {
        $element = $this->owner;

        if ($element->duplicateOf !== null) {
            return !empty($element->duplicateOf->getModifiedAttributes());
        }

        return !empty($element->getDirtyAttributes());
    }

    /**
     * Returns the handles of the custom fields that have changed.
     *
     * @return string[]
     */
    public function getChangedFields(): array
    {
        $element = $this->owner;

        if ($element->duplicateOf !== null) {
            return $element->duplicateOf->getModifiedFields();
        }

        // This only works for elements that are saved from a duplicate.
        // It always returns `true` for elements that don’t support drafts.
        return $element->getDirtyFields();
    }
}
