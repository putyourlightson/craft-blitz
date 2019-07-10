<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\drivers\integrations;

use nystudio107\seomatic\events\InvalidateContainerCachesEvent;
use nystudio107\seomatic\services\MetaContainers;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\models\SiteUriModel;
use yii\base\Event;

class SeomaticIntegration implements IntegrationInterface
{
    // Static Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function getRequiredPluginHandles(): array
    {
        return ['seomatic'];
    }

    /**
     * @inheritdoc
     */
    public static function getRequiredClasses(): array
    {
        return ['nystudio107\seomatic\services\MetaContainers'];
    }

    /**
     * @inheritdoc
     */
    public static function registerEvents()
    {
        Event::on(MetaContainers::class, MetaContainers::EVENT_INVALIDATE_CONTAINER_CACHES,
            function(InvalidateContainerCachesEvent $event) {
                if ($event->uri === null && $event->siteId === null && $event->sourceId === null) {
                    // Refresh all cache
                    Blitz::$plugin->refreshCache->refreshAll();
                }
                elseif ($event->sourceId !== null) {
                    // TODO: implement refreshing cache for source URIs
                    // Refresh cache for source
                    //$siteUris = $this->_getSourceSiteUris($event->sourceId);
                    //Blitz::$plugin->refreshCache->refreshSiteUris($siteUris);

                    // Refresh all cache
                    Blitz::$plugin->refreshCache->refreshAll();
                }
                elseif ($event->uri !== null && $event->siteId !== null) {
                    // Refresh site URI
                    $siteUri = new SiteUriModel([
                        'siteId' => $event->siteId,
                        'uri' => $event->uri,
                    ]);

                    Blitz::$plugin->refreshCache->refreshSiteUris([$siteUri]);
                }
            }
        );
    }

    // Private Methods
    // =========================================================================

    /**
     * Returns the site URIs for the given source ID
     *
     * @param int $sourceId
     *
     * @return SiteUriModel[]
     */
    private static function _getSourceSiteUris(int $sourceId): array
    {
        $siteUris = [];

        return $siteUris;
    }
}
