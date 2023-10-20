<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\helpers;

use craft\base\BlockElementInterface;
use craft\base\Element;
use craft\elements\Category;
use craft\elements\Entry;
use craft\elements\GlobalSet;
use craft\elements\Tag;
use craft\elements\User;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\events\RegisterLiveStatusesEvent;
use putyourlightson\blitz\events\RegisterNonCacheableElementTypesEvent;
use putyourlightson\blitz\events\RegisterSourceIdAttributesEvent;
use yii\base\Event;

class ElementTypeHelper
{
    /**
     * @event RegisterNonCacheableElementTypesEvent
     */
    public const EVENT_REGISTER_NON_CACHEABLE_ELEMENT_TYPES = 'registerNonCacheableElementTypes';

    /**
     * @event RegisterSourceIdAttributesEvent
     */
    public const EVENT_REGISTER_SOURCE_ID_ATTRIBUTES = 'registerSourceIdAttributes';

    /**
     * @event RegisterLiveStatusesEvent
     */
    public const EVENT_REGISTER_LIVE_STATUSES = 'registerLiveStatuses';

    /**
     * @const string[]
     */
    public const NON_CACHEABLE_ELEMENT_TYPES = [
        GlobalSet::class,
        'benf\neo\elements\Block',
        'craft\commerce\elements\Order',
        'putyourlightson\campaign\elements\ContactElement',
    ];

    /**
     * @const string[]
     */
    public const SOURCE_ID_ATTRIBUTES = [
        Entry::class => 'sectionId',
        Category::class => 'groupId',
        Tag::class => 'groupId',
        'craft\commerce\elements\Product' => 'typeId',
        'putyourlightson\campaign\elements\CampaignElement' => 'campaignTypeId',
        'putyourlightson\campaign\elements\MailingListElement' => 'mailingListTypeId',
    ];

    /**
     * @const string[]
     *
     * @deprecated in 4.6.1
     */
    public const LIVE_STATUSES = [
        Entry::class => Entry::STATUS_LIVE,
        User::class => User::STATUS_ACTIVE,
        'craft\commerce\elements\Product' => 'live',
    ];

    /**
     * @var string[]|null
     */
    private static ?array $_nonCacheableElementTypes = null;

    /**
     * @var string[]|null
     */
    private static ?array $_sourceIdAttributes = null;

    /**
     * @var string[]|null
     */
    private static ?array $_liveStatuses = null;

    /**
     * Returns whether the element type is cacheable.
     */
    public static function getIsCacheableElementType(string $elementType = null): bool
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
     * Returns the source ID attribute for the element type.
     */
    public static function getSourceIdAttribute(string $elementType = null): ?string
    {
        if ($elementType === null) {
            return null;
        }

        $sourceIdAttributes = self::getSourceIdAttributes();

        return $sourceIdAttributes[$elementType] ?? null;
    }

    /**
     * Returns the live status for the element type.
     */
    public static function getLiveStatus(string $elementType = null): ?string
    {
        if ($elementType === null) {
            return null;
        }

        $liveStatuses = self::getLiveStatuses();

        return $liveStatuses[$elementType] ?? Element::STATUS_ENABLED;
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

    /**
     * Returns the live statuses for element types.
     *
     * @return string[]
     */
    public static function getLiveStatuses(): array
    {
        if (self::$_liveStatuses !== null) {
            return self::$_liveStatuses;
        }

        $event = new RegisterLiveStatusesEvent([
            'liveStatuses' => Blitz::$plugin->settings->liveStatuses,
        ]);
        Event::trigger(self::class, self::EVENT_REGISTER_LIVE_STATUSES, $event);

        self::$_liveStatuses = array_merge(
            $event->liveStatuses
        );

        return self::$_liveStatuses;
    }
}
