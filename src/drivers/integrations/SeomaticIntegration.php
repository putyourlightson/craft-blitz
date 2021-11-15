<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\drivers\integrations;

use craft\elements\db\ElementQuery;
use craft\elements\db\ElementQueryInterface;
use nystudio107\seomatic\events\InvalidateContainerCachesEvent;
use nystudio107\seomatic\Seomatic;
use nystudio107\seomatic\services\MetaContainers;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\records\CacheRecord;
use yii\base\Event;

class SeomaticIntegration extends BaseIntegration
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
        // Set up invalidate container caches event listeners
        Event::on(MetaContainers::class, MetaContainers::EVENT_INVALIDATE_CONTAINER_CACHES,
            function(InvalidateContainerCachesEvent $event) {
                if ($event->uri === null && $event->siteId === null && $event->sourceId === null && $event->sourceType === null) {
                    // Refresh the entire cache.
                    Blitz::$plugin->refreshCache->refreshAll();
                }
                elseif ($event->uri === null && $event->siteId !== null && $event->sourceId !== null && $event->sourceType !== null) {
                    // Refresh the cache for the provided source only.
                    /** @var ElementQuery $elementQuery */
                    $elementQuery = self::_getElementQuery($event->siteId, $event->sourceId, $event->sourceType);
                    $elementIds = $elementQuery->ids();

                    if (!empty($elementIds)) {
                        Blitz::$plugin->refreshCache->addElementIds($elementQuery->elementType, $elementIds);

                        if (Blitz::$plugin->refreshCache->batchMode === false) {
                            Blitz::$plugin->refreshCache->refresh();
                        }
                    }
                }
                // Don't refresh cache for single URIs, since Blitz takes care of that for us.
            }
        );
    }

    // Private Methods
    // =========================================================================

    /**
     * Returns the element IDs for the given site, source and type.
     *
     * @param int $siteId
     * @param int $sourceId
     * @param string $sourceType
     *
     * @return ElementQueryInterface
     */
    private static function _getElementQuery(int $siteId, int $sourceId, string $sourceType): ElementQueryInterface
    {
        $metaBundle = Seomatic::$plugin->metaBundles->getMetaBundleBySourceId($sourceType, $sourceId, $siteId);

        $seoElement = Seomatic::$plugin->seoElements->getSeoElementByMetaBundleType($metaBundle->sourceBundleType);

        return $seoElement::sitemapElementsQuery($metaBundle);
    }

    /**
     * Returns cache IDs from a given URI.
     *
     * @param string $uri
     * @param int $siteId
     *
     * @return int[]
     */
    private static function _getCacheIdsFromUri(string $uri, int $siteId): array
    {
        return CacheRecord::find()
            ->select('id')
            ->where([
                'uri' => $uri,
                'siteId' => $siteId,
            ])
            ->column();
    }
}
