<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\records;

use craft\db\ActiveRecord;
use yii\db\ActiveQueryInterface;

/**
 * @property int $cacheId
 * @property int $relatedCacheId
 * @property-read ActiveQueryInterface $relatedCaches
 */
class RelatedCacheRecord extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%blitz_relatedcaches}}';
    }

    /**
     * Returns the associated related caches
     */
    public function getRelatedCaches(): ActiveQueryInterface
    {
        return $this->hasMany(CacheRecord::class, ['relatedCacheId' => 'id']);
    }
}
