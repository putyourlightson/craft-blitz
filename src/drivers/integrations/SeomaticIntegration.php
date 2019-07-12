<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\drivers\integrations;

use nystudio107\seomatic\events\InvalidateContainerCachesEvent;
use nystudio107\seomatic\Seomatic;
use nystudio107\seomatic\services\MetaContainers;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\helpers\SiteUriHelper;
use putyourlightson\blitz\models\SiteUriModel;
use yii\base\Event;

class SeomaticIntegration implements IntegrationInterface
{
    // Static Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function getRequiredPlugins(): array
    {
        return [
            ['handle' => 'seomatic', 'version' => '3.2.14']
        ];
    }

    /**
     * @inheritdoc
     */
    public static function registerEvents()
    {
        Event::on(MetaContainers::class, MetaContainers::EVENT_INVALIDATE_CONTAINER_CACHES,
            function(InvalidateContainerCachesEvent $event) {
                if ($event->uri === null && $event->siteId === null) {
                    // Refresh all cache
                    //Blitz::$plugin->refreshCache->refreshAll();
                }
                elseif ($event->siteId !== null && $event->sourceId !== null && $event->sourceType) {
                    // Refresh cache for source
                    $siteUris = self::_getSourceSiteUris($event->siteId, $event->sourceId, $event->sourceType);
                    Blitz::$plugin->refreshCache->refreshSiteUris($siteUris);
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
     * @param int $siteId
     * @param int $sourceId
     * @param string $sourceType
     *
     * @return SiteUriModel[]
     */
    private static function _getSourceSiteUris(int $siteId, int $sourceId, string $sourceType): array
    {
        $metaBundle = Seomatic::$plugin->metaBundles->getMetaBundleBySourceId(
            $sourceType,
            $sourceId,
            $siteId
        );

        $seoElement = Seomatic::$plugin->seoElements->getSeoElementByMetaBundleType($metaBundle->sourceBundleType);
        $query = $seoElement::sitemapElementsQuery($metaBundle);
        $elementIds = $query->ids();

        $siteUris = SiteUriHelper::getElementSiteUris($elementIds);

        return $siteUris;
    }
}
