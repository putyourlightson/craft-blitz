<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\drivers\integrations;

use Craft;
use craft\base\Component;
use nystudio107\seomatic\events\InvalidateContainerCachesEvent;
use nystudio107\seomatic\services\MetaContainers;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\models\SiteUriModel;
use yii\base\Event;

class SeomaticIntegration extends Component
{
    // Constants
    // =========================================================================

    const PLUGIN_HANDLE = 'seomatic';

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init()
    {
        if (Craft::$app->getPlugins()->getPlugin(self::PLUGIN_HANDLE) === null) {
            return;
        }

        Event::on(MetaContainers::class, MetaContainers::EVENT_INVALIDATE_CONTAINER_CACHES,
            function(InvalidateContainerCachesEvent $event) {
                if ($event->uri !== null) {
                    $siteUri = new SiteUriModel([
                        'siteId' => $event->siteId,
                        'uri' => $event->uri,
                    ]);

                    Blitz::$plugin->refreshCache->refreshSiteUris([$siteUri]);
                }
            }
        );
    }
}
