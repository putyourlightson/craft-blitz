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
 * Detects whether an element has changed.
 *
 * @since 3.6.0
 *
 * @property-read bool $hasChanged
 * @property-read bool $hasBeenDeleted
 * @property-read bool $hasStatusChanged
 * @property-read bool $hasAssetFileChanged
 * @property-read bool $hasRefreshableStatus
 * @property Element $owner
 */
class ElementChangedBehavior extends Behavior
{
    // Constants
    // =========================================================================

    /**
     * @const string
     */
    const BEHAVIOR_NAME = 'elementChanged';

    // Properties
    // =========================================================================

    /**
     * @var Element|null The original element.
     */
    public $originalElement = null;

    /**
     * @var bool
     */
    public $isDeleted = false;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function attach($owner)
    {
        parent::attach($owner);

        $element = $this->owner;

        // Don't proceed if this is a new element
        if ($element->id === null) {
            return;
        }

        $this->originalElement = Craft::$app->getElements()->getElementById($element->id, get_class($element), $element->siteId);

        $element->on(Element::EVENT_AFTER_DELETE, function() {
            $this->isDeleted = true;
        });
    }

    /**
     * Returns whether the element's fields, attributes or status have changed.
     *
     * @return bool
     */
    public function getHasChanged(): bool
    {
        $element = $this->owner;

        /**
         * Craft 3.7.5 introduced detection of first save.
         */
        if ($element->firstSave) {
            return true;
        }

        /**
         * Craft 3.7 can save canonical entries by duplicating a draft or revision, so we need to additionally check whether the original element has any modified
         *  attributes or fields.
         */
        if (!empty($element->getDirtyAttributes()) || !empty($element->getDirtyFields())) {
            return true;
        }

        $original = $element->duplicateOf;

        if ($original !== null) {
            if (!empty($original->getModifiedAttributes()) || !empty($original->getModifiedFields())) {
                return true;
            }
        }

        if ($this->getHasBeenDeleted()) {
            return true;
        }

        if ($this->getHasStatusChanged()) {
            return true;
        }

        if ($this->getHasAssetFileChanged()) {
            return true;
        }

        return false;
    }

    /**
     * Returns whether the element has been deleted.
     */
    public function getHasBeenDeleted(): bool
    {
        return $this->isDeleted;
    }

    /**
     * Returns whether the element's status has changed.
     *
     * @return bool
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
     * Returns whether the element is an asset and its file has changed.
     *
     * @since 3.14.0
     */
    public function getHasAssetFileChanged(): bool
    {
        $element = $this->owner;

        if (!($element instanceof Asset) || !($this->originalElement instanceof Asset)) {
            return false;
        }

        if ($element->scenario == Asset::SCENARIO_REPLACE) {
            return true;
        }

        if ($element->filename != $this->originalElement->filename) {
            return true;
        }

        if ($element->kind === Asset::KIND_IMAGE) {
            if ($element->getDimensions() != $this->originalElement->getDimensions()) {
                return true;
            }

            if ($element->getDimensions() != $this->originalElement->getDimensions()) {
                return true;
            }

            // Comparing floats is problematic, so convert to a fixed precision first.
            // https://www.php.net/manual/en/language.types.float.php
            $precision = 5;
            $originalFocalPoint = $this->originalElement->getFocalPoint();
            $originalFocalPoint = [
                number_format($originalFocalPoint['x'], $precision),
                number_format($originalFocalPoint['y'], $precision),
            ];
            $focalPoint = $element->getFocalPoint();
            $focalPoint = [
                number_format($focalPoint['x'], $precision),
                number_format($focalPoint['y'], $precision),
            ];

            return $focalPoint != $originalFocalPoint;
        }

        return false;
    }

    /**
     * Returns whether the element has a live, pending or expired status.
     *
     * @return bool
     */
    public function getHasRefreshableStatus(): bool
    {
        $elementStatus = $this->owner->getStatus();
        $liveStatus = ElementTypeHelper::getLiveStatus(get_class($this->owner));
        $refreshableStatuses = [
            $liveStatus,
            'live',
            'active',
            'pending',
            'expired',
        ];

        return in_array($elementStatus, $refreshableStatuses);
    }
}
