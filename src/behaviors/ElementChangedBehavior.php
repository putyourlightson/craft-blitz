<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\behaviors;

use Craft;
use craft\base\Element;
use putyourlightson\blitz\helpers\ElementTypeHelper;
use yii\base\Behavior;

/**
 * This class attaches behavior to detect whether an element has changed.
 *
 * @since 3.6.0
 *
 * @property-read bool $hasChanged
 * @property-read bool $hasStatusChanged
 * @property-read bool $hasLiveOrExpiredStatus
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
     * @var string|null
     */
    public $previousStatus;

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

        /** @var Element|null $originalElement */
        $originalElement = Craft::$app->getElements()->getElementById($element->id, get_class($element), $element->siteId);

        if ($originalElement !== null) {
            $this->previousStatus = $originalElement->getStatus();
        }

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
        // TODO: remove in 4.0.0
        // Detection of changes not possible before Craft 3.4, therefore assume true
        if (version_compare(Craft::$app->getVersion(), '3.4', '<')) {
            return true;
        }

        // TODO: remove in 4.0.0
        if (version_compare(Craft::$app->getVersion(), '3.7.0', '>=')) {
            // Detection of first save not possible before Craft 3.7.5, therefore assume true
            if (version_compare(Craft::$app->getVersion(), '3.7.5', '<')) {
                return true;
            }
            elseif ($this->owner->firstSave) {
                return true;
            }
        }

        if ($this->isDeleted) {
            return true;
        }

        if ($this->getHasStatusChanged()) {
            return true;
        }

        if (!empty($this->owner->getDirtyAttributes()) || !empty($this->owner->getDirtyFields())) {
            return true;
        }

        return false;
    }

    /**
     * Returns whether the element's status has changed.
     *
     * @return bool
     */
    public function getHasStatusChanged(): bool
    {
        return $this->previousStatus === null || $this->previousStatus != $this->owner->getStatus();
    }

    /**
     * Returns whether the element has a live or expired status.
     *
     * @return bool
     */
    public function getHasLiveOrExpiredStatus(): bool
    {
        $elementStatus = $this->owner->getStatus();
        $liveStatus = ElementTypeHelper::getLiveStatus(get_class($this->owner));

        return ($elementStatus == $liveStatus || $elementStatus == 'expired');
    }
}
