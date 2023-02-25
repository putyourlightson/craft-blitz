<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\records;

use craft\db\ActiveQuery;
use craft\db\ActiveRecord;

/**
 * @property int $cacheId
 * @property string $tag
 * @property-read CacheRecord $cache
 */
class CacheTagRecord extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%blitz_cachetags}}';
    }

    /**
     * Returns the associated cache
     */
    public function getCache(): ActiveQuery
    {
        return $this->hasOne(CacheRecord::class, ['id' => 'cacheId']);
    }
}
