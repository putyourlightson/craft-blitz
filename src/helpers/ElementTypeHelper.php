<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\helpers;

use craft\base\BlockElementInterface;
use craft\elements\Category;
use craft\elements\Entry;
use craft\elements\GlobalSet;
use craft\elements\Tag;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\events\RegisterNonCacheableElementTypesEvent;
use putyourlightson\blitz\events\RegisterSourceIdAttributesEvent;
use yii\base\Event;

class ElementTypeHelper
{
    // Constants
    // =========================================================================

    /**
     * @event RegisterNonCacheableElementTypesEvent
     */
    const EVENT_REGISTER_NON_CACHEABLE_ELEMENT_TYPES = 'registerNonCacheableElementTypes';

    /**
     * @event RegisterSourceIdAttributesEvent
     */
    const EVENT_REGISTER_SOURCE_ID_ATTRIBUTES = 'registerSourceIdAttributes';

    /**
     * @const string[]
     */
    const NON_CACHEABLE_ELEMENT_TYPES = [
        GlobalSet::class,
        'benf\neo\elements\Block',
        'craft\commerce\elements\Order',
        'putyourlightson\campaign\elements\ContactElement',
    ];

    /**
     * @const string[]
     */
    const SOURCE_ID_ATTRIBUTES = [
        Entry::class => 'sectionId',
        Category::class => 'groupId',
        Tag::class => 'groupId',
        'craft\commerce\elements\Product' => 'typeId',
        'putyourlightson\campaign\elements\CampaignElement' => 'campaignTypeId',
        'putyourlightson\campaign\elements\MailingListElement' => 'mailingListTypeId',
    ];

    // Properties
    // =========================================================================

    /**
     * @var string[]|null
     */
    private static $_nonCacheableElementTypes;

    /**
     * @var string[]|null
     */
    private static $_sourceIdAttributes;

    // Public Methods
    // =========================================================================

    /**
     * Returns whether the element type is cacheable.
     *
     * @param string|null $elementType
     *
     * @return bool
     */
    public static function getIsCacheableElementType($elementType): bool
    {
        if ($elementType === null) {
            return false;
        }

        // Don't proceed if this is a block element type
        if (is_subclass_of($elementType, BlockElementInterface::class)) {
            return false;
        }

        // Don't proceed if this is a non cacheable element type
        if (in_array($elementType, self::getNonCacheableElementTypes())) {
            return false;
        }

        return true;
    }

    /**
     * Returns the source ID attribute for the element types.
     *
     * @param string|null $elementType
     *
     * @return string|null
     */
    public static function getSourceIdAttribute($elementType)
    {
        if ($elementType === null) {
            return null;
        }

        $sourceIdAttributes = self::getSourceIdAttributes();

        return $sourceIdAttributes[$elementType] ?? null;
    }

    /**
     * Returns non cacheable element types.
     *
     * @return string[]
     */
    public static function getNonCacheableElementTypes(): array
    {
        if (self::$_nonCacheableElementTypes !== null) {
            return self::$_nonCacheableElementTypes;
        }

        $event = new RegisterNonCacheableElementTypesEvent([
            'elementTypes' => Blitz::$plugin->settings->nonCacheableElementTypes,
        ]);
        Event::trigger(self::class, self::EVENT_REGISTER_NON_CACHEABLE_ELEMENT_TYPES, $event);

        self::$_nonCacheableElementTypes = array_merge(
            self::NON_CACHEABLE_ELEMENT_TYPES,
            $event->elementTypes
        );

        return self::$_nonCacheableElementTypes;
    }

    /**
     * Returns the source ID attributes for element types.
     *
     * @return string[]
     */
    public static function getSourceIdAttributes(): array
    {
        if (self::$_sourceIdAttributes !== null) {
            return self::$_sourceIdAttributes;
        }

        $event = new RegisterSourceIdAttributesEvent([
            'sourceIdAttributes' => Blitz::$plugin->settings->sourceIdAttributes,
        ]);
        Event::trigger(self::class, self::EVENT_REGISTER_SOURCE_ID_ATTRIBUTES, $event);

        self::$_sourceIdAttributes = array_merge(
            self::SOURCE_ID_ATTRIBUTES,
            $event->sourceIdAttributes
        );

        return self::$_sourceIdAttributes;
    }
}
