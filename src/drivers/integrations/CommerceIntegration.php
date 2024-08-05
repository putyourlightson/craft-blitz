<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\drivers\integrations;

use craft\commerce\elements\Order;
use craft\commerce\elements\Variant;
use putyourlightson\blitz\Blitz;
use yii\base\Event;

/**
 * @since 4.2.0
 */
class CommerceIntegration extends BaseIntegration
{
    /**
     * @inheritdoc
     */
    public static function getRequiredPlugins(): array
    {
        return [
            ['handle' => 'commerce', 'version' => '4.0.0'],
        ];
    }

    /**
     * @inheritdoc
     */
    public static function registerEvents(): void
    {
        // Refresh variants on order completion so that their stock is updated.
        // https://github.com/putyourlightson/craft-blitz/issues/432
        Event::on(Order::class, Order::EVENT_AFTER_COMPLETE_ORDER,
            function(Event $event) {
                /** @var Order $order */
                $order = $event->sender;
                foreach ($order->getLineItems() as $lineItem) {
                    $purchasable = $lineItem->getPurchasable();
                    if ($purchasable instanceof Variant && $purchasable->inventoryTracked) {
                        Blitz::$plugin->refreshCache->addElement($purchasable);
                    }
                }
            }
        );
    }
}
