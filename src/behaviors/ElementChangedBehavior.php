<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\behaviors;

use Craft;
use craft\base\Element;
use craft\base\Field;
use craft\elements\Asset;
use craft\elements\Entry;
use craft\helpers\ElementHelper;
use putyourlightson\blitz\helpers\ElementTypeHelper;
use yii\base\Behavior;

/**
 * Detects whether and what specifically about an element has changed.
 *
 * @since 3.6.0
 *
 * @property Element $owner
 * @property-read bool $hasChanged
 * @property-read bool $hasBeenDeleted
 * @property-read bool $hasStatusChanged
 * @property-read bool $hasAssetFileChanged
 * @property-read bool $hasRefreshableStatus
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
     * @var array<int,bool> The original element’s site statuses.
     */
    public array $originalElementSiteStatuses = [];

    /**
     * @var string[] The attributes that changed.
     */
    public array $changedAttributes = [];

    /**
     * @var string[] The field handles of the custom fields that changed.
     */
    public array $changedFieldsHandles = [];

    /**
     * @var bool Whether the element was caused to change specifically by attributes.
     */
    public bool $isChangedByAttributes = false;

    /**
     * @var bool Whether the element was caused to change specifically by fields.
     */
    public bool $isChangedByFields = false;

    /**
     * @var bool Whether the element is an asset and its file has changed.
     */
    public bool $isChangedByAssetFile = false;

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

        if ($this->originalElement !== null) {
            $this->originalElementSiteStatuses = ElementHelper::siteStatusesForElement($this->originalElement);
        }
    }

    /**
     * Returns whether the element has changed.
     */
    public function getHasChanged(): bool
    {
        $element = $this->owner;

        $this->changedAttributes = $this->getChangedAttributes();
        $this->changedFieldsHandles = $this->getChangedFieldHandles();

        if ($element->firstSave) {
            return true;
        }

        if ($this->getHasBeenDeleted()) {
            return true;
        }

        if ($this->getHasStatusChanged()) {
            return true;
        }

        if ($this->getHasAssetFileChanged()) {
            $this->isChangedByAssetFile = true;

            return true;
        }

        if (!empty($this->changedAttributes)) {
            $this->isChangedByAttributes = true;

            return true;
        }

        if (!empty($this->changedFieldsHandles)) {
            $this->isChangedByFields = true;

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
     * Returns sites affected by translatable content changes, or null for changes whose scope is global or unknown.
     *
     * @return int[]|null
     * @since 5.13.0
     */
    public function getAffectedSiteIds(): ?array
    {
        $element = $this->owner;

        if (!$element::isLocalized() || $this->originalElement === null
            || $element->firstSave || $element->isNewForSite || $element->propagateAll || $element->propagateRequired
            || $element->resaving || $this->getHasBeenDeleted() || $this->getHasStatusChanged() || $this->getHasAssetFileChanged()
            || (!$this->isChangedByAttributes && !$this->isChangedByFields)
        ) {
            return null;
        }

        // Other attributes (including timestamps, structure positions and publication dates) are shared or have unknown scope.
        foreach ($this->changedAttributes as $attribute) {
            if (!in_array($attribute, ['title', 'slug', 'uri'], true)) {
                return null;
            }
        }

        $hasTitleChanged = in_array('title', $this->changedAttributes, true);
        $hasSlugChanged = in_array('slug', $this->changedAttributes, true);
        if ($hasTitleChanged || $hasSlugChanged) {
            if (!($element instanceof Entry)) {
                return null;
            }
            $entryType = $element->getType();
            if (($hasTitleChanged && $entryType->titleTranslationMethod === Field::TRANSLATION_METHOD_CUSTOM)
                || ($hasSlugChanged && $entryType->slugTranslationMethod === Field::TRANSLATION_METHOD_CUSTOM)) {
                return null;
            }
        }

        $fields = [];
        foreach ($this->changedFieldsHandles as $handle) {
            $field = $element->getFieldLayout()?->getFieldByHandle($handle);
            if (!($field instanceof Field) || $field->translationMethod === Field::TRANSLATION_METHOD_CUSTOM) {
                return null;
            }
            $fields[] = $field;
        }

        // The element’s site is implicitly affected.
        $affectedSites = [$element->siteId];

        foreach (ElementHelper::supportedSitesForElement($element) as $site) {
            if (!in_array($site['siteId'], $affectedSites, true)) {
                $variant = clone $element;
                $variant->siteId = $site['siteId'];
                $isAffected = false;
                if ($hasTitleChanged) {
                    $isAffected = $variant->getTitleTranslationKey() === $element->getTitleTranslationKey();
                }
                if (!$isAffected && $hasSlugChanged) {
                    $isAffected = $variant->getSlugTranslationKey() === $element->getSlugTranslationKey();
                }
                if (!$isAffected) {
                    foreach ($fields as $field) {
                        if ($field->getTranslationKey($variant) === $field->getTranslationKey($element)) {
                            $isAffected = true;
                            break;
                        }
                    }
                }
                if ($isAffected) {
                    $affectedSites[] = $site['siteId'];
                }
            }
        }

        return $affectedSites;
    }

    /**
     * Returns whether the element’s status or any site statuses have changed.
     */
    public function getHasStatusChanged(): bool
    {
        $element = $this->owner;

        if ($this->originalElement === null) {
            return false;
        }

        if ($element->getStatus() != $this->originalElement->getStatus()) {
            return true;
        }

        $supportedSites = ElementHelper::supportedSitesForElement($element);

        foreach ($supportedSites as $supportedSite) {
            $siteId = $supportedSite['siteId'];
            $siteStatus = $element->getEnabledForSite($siteId);
            $originalSiteStatus = $this->originalElementSiteStatuses[$siteId] ?? null;

            if (
                $siteStatus !== null
                && $originalSiteStatus !== null
                && $siteStatus !== $originalSiteStatus
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns whether the element is an asset and its file has changed.
     */
    public function getHasAssetFileChanged(): bool
    {
        $element = $this->owner;

        if (!($element instanceof Asset) || !($this->originalElement instanceof Asset)) {
            return false;
        }

        if (!$element->getVolume()->getFs()->hasUrls) {
            return false;
        }

        if ($element->scenario == Asset::SCENARIO_REPLACE) {
            return true;
        }

        if ($element->filename != $this->originalElement->filename) {
            return true;
        }

        if ($element->folderId != $this->originalElement->folderId) {
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
     */
    public function getHasRefreshableStatus(): bool
    {
        $element = $this->owner;
        $elementStatus = $element->getStatus();
        $liveStatus = ElementTypeHelper::getLiveStatus($element::class);
        $refreshableStatuses = [
            $liveStatus,
            'live',
            'active',
            'pending',
            'expired',
        ];

        return in_array($elementStatus, $refreshableStatuses);
    }

    /**
     * Returns the attributes that have changed.
     */
    private function getChangedAttributes(): array
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
     * Returns the field handles of the custom fields that have changed.
     *
     * @return string[]
     */
    private function getChangedFieldHandles(): array
    {
        $element = $this->owner;

        if ($element->duplicateOf === null) {
            // Only elements that support drafts can track changed fields:
            // https://github.com/craftcms/cms/discussions/12667
            $changedFieldHandles = $element->getDirtyFields();
        } else {
            $changedFieldHandles = $element->duplicateOf->getModifiedFields();
        }

        return $changedFieldHandles;
    }
}
