<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\services;

use Craft;
use craft\base\Component;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\models\SiteUriModel;
use putyourlightson\blitz\records\CacheTagRecord;

class CacheTagsService extends Component
{
    // Public Methods
    // =========================================================================

    /**
     * Returns all unique tags.
     *
     * @return string[]
     */
    public function getAllTags(): array
    {
        return CacheTagRecord::find()
            ->select('tag')
            ->groupBy('tag')
            ->column();
    }

    /**
     * Returns tags for the given site URI.
     *
     * @param SiteUriModel $siteUri
     *
     * @return string[]
     */
    public function getSiteUriTags(SiteUriModel $siteUri): array
    {
        return CacheTagRecord::find()
            ->select('tag')
            ->joinWith('cache')
            ->where($siteUri->toArray())
            ->groupBy('tag')
            ->column();
    }

    /**
     * Returns cache IDs for the given tags.
     *
     * @param string|string[] $tags
     *
     * @return int[]
     */
    public function getCacheIds($tags): array
    {
        return CacheTagRecord::find()
            ->select('cacheId')
            ->where(['tag' => $tags])
            ->groupBy('cacheId')
            ->column();
    }

    /**
     * Saves one or more tags given a cache ID.
     *
     * @param string|string[] $tags
     * @param int $cacheId
     */
    public function save($tags, int $cacheId)
    {
        if (!is_array($tags)) {
            $tags = [$tags];
        }

        $values = [];

        foreach ($tags as $tag) {
            $values[] = [$cacheId, $tag];
        }

        Craft::$app->getDb()->createCommand()
            ->batchInsert(CacheTagRecord::tableName(),
                ['cacheId', 'tag'],
                $values,
                false)
            ->execute();
    }
}