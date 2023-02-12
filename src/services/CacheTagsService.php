<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\services;

use Craft;
use craft\base\Component;
use putyourlightson\blitz\models\SiteUriModel;
use putyourlightson\blitz\records\CacheTagRecord;
use putyourlightson\campaign\helpers\StringHelper;

/**
 * @property-read string[] $allTags
 */
class CacheTagsService extends Component
{
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
     * @param string[] $tags
     * @return int[]
     */
    public function getCacheIds(array $tags): array
    {
        if (empty($tags)) {
            return [];
        }

        return CacheTagRecord::find()
            ->select('cacheId')
            ->where(['tag' => $tags])
            ->groupBy('cacheId')
            ->column();
    }

    /**
     * Saves one or more tags given a cache ID.
     *
     * @param string[]|string $tags
     */
    public function saveTags(array|string $tags, int $cacheId): void
    {
        $values = [];
        $tags = is_string($tags) ? StringHelper::split($tags) : $tags;

        foreach ($tags as $tag) {
            $values[] = [$cacheId, $tag];
        }

        Craft::$app->getDb()->createCommand()
            ->batchInsert(
                CacheTagRecord::tableName(),
                ['cacheId', 'tag'],
                $values,
            )
            ->execute();
    }
}
