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
 * @property-read bool $hasStatusChanged
 * @property-read bool $hasChanged
 * @property-read bool $hasLiveStatus
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
    public $deleted = false;

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
            $this->deleted = true;
        });
    }

    /**
     * Returns whether the element's fields, attributes or status have changed.
     *
     * @return bool
     */
    public function getHasChanged(): bool
    {
        // Only works with Craft 3.4 to 3.6 using delta changes feature
        // TODO: remove in 3.11.0
        if (version_compare(Craft::$app->getVersion(), '3.4', '<')
            || version_compare(Craft::$app->getVersion(), '3.6.99', '>')
        ) {
            return true;
        }

        if ($this->deleted) {
            return true;
        }

        if ($this->getHasStatusChanged()) {
            return true;
        }

        return !empty($this->owner->getDirtyAttributes()) || !empty($this->owner->getDirtyFields());
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
