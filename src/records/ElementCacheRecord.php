<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\records;

use craft\db\ActiveQuery;
use craft\db\ActiveRecord;

/**
 * @property int $cacheId
 * @property int $elementId
 * @property-read CacheRecord $cache
 * @property-read ElementFieldCacheRecord[] $elementFieldCaches
 */
class ElementCacheRecord extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%blitz_elementcaches}}';
    }

    /**
     * Returns the associated cache
     */
    public function getCache(): ActiveQuery
    {
        return $this->hasOne(CacheRecord::class, ['id' => 'cacheId']);
    }

    /**
     * Returns the associated element field cache records
     */
    public function getElementFieldCaches(): ActiveQuery
    {
        return $this->hasMany(ElementFieldCacheRecord::class, [
            'cacheId' => 'cacheId',
            'elementId' => 'elementId',
        ]);
    }
}
