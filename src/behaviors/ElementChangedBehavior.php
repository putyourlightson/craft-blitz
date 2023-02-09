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
 * @property-read bool $hasStatusChanged
 * @property-read bool $hasFocalPointChanged
 * @property-read bool $hasRefreshableStatus
 * @property Element $owner
 */
class ElementChangedBehavior extends Behavior
{
    /**
     * @const string
     */
    public const BEHAVIOR_NAME = 'elementChanged';

    /**
     * @var string|null The previous status of the element.
     */
    public ?string $previousStatus = null;

    /**
     * @var array|null The previous focal point of the element, if an asset.
     */
    public ?array $previousFocalPoint = null;

    /**
     * @var bool Whether the element was deleted.
     */
    public bool $isDeleted = false;

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

        $originalElement = Craft::$app->getElements()->getElementById($element->id, get_class($element), $element->siteId);

        if ($originalElement !== null) {
            $this->previousStatus = $originalElement->getStatus();

            if ($originalElement instanceof Asset) {
                $this->previousFocalPoint = $originalElement->focalPoint;
            }
        }

        $element->on(Element::EVENT_AFTER_DELETE, function() {
            $this->isDeleted = true;
        });
    }

    /**
     * Returns whether the element's fields, attributes or status have changed.
     */
    public function getHasChanged(): bool
    {
        $element = $this->owner;

        /**
         * Craft 3.7.5 introduced detection of first save. Craft 3.7 can save
         * canonical entries by duplicating a draft or revision, so we need to
         * additionally check whether the original element has any modified
         * attributes or fields.
         */
        if ($element->firstSave) {
            return true;
        }

        if (!empty($element->getDirtyAttributes())) {
            return true;
        }

        $original = $element->duplicateOf;

        if ($original === null) {
            if (!empty($element->getDirtyFields())) {
                return true;
            }
        } else {
            if (!empty($original->getModifiedAttributes()) || !empty($original->getModifiedFields())) {
                return true;
            }
        }

        if ($this->isDeleted) {
            return true;
        }

        if ($this->getHasStatusChanged()) {
            return true;
        }

        if ($this->getHasFocalPointChanged()) {
            return true;
        }

        return false;
    }

    /**
     * Returns whether the element's status has changed.
     */
    public function getHasStatusChanged(): bool
    {
        return $this->previousStatus === null || $this->previousStatus != $this->owner->getStatus();
    }

    /**
     * Returns whether the element's focal point has changed, if an asset, which takes
     * image cropping and rotation into account too.
     */
    public function getHasFocalPointChanged(): bool
    {
        if ($this->previousFocalPoint === null || !($this->owner instanceof Asset)) {
            return false;
        }

        // Comparing floats is problematic, so we convert to a fixed precision first.
        // https://www.php.net/manual/en/language.types.float.php
        $precision = 5;
        $previousFocalPoint = [
            number_format($this->previousFocalPoint['x'], $precision),
            number_format($this->previousFocalPoint['y'], $precision),
        ];
        $focalPoint = [
            number_format($this->owner->focalPoint['x'], $precision),
            number_format($this->owner->focalPoint['y'], $precision),
        ];

        return $previousFocalPoint != $focalPoint;
    }

    /**
     * Returns whether the element has a live, pending or expired status.
     */
    public function getHasRefreshableStatus(): bool
    {
        $elementStatus = $this->owner->getStatus();
        $liveStatus = ElementTypeHelper::getLiveStatus(get_class($this->owner));
        $refreshableStatuses = [
            $liveStatus,
            'pending',
            'expired',
        ];

        return in_array($elementStatus, $refreshableStatuses);
    }
}
