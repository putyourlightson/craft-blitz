<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\records;

use craft\db\ActiveRecord;
use yii\db\ActiveQueryInterface;

/**
 * @property int $cacheId
 * @property int $elementId
 * @property CacheRecord $cache
 * @property ElementFieldCacheRecord[] $elementFieldCaches
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
    public function getCache(): ActiveQueryInterface
    {
        return $this->hasOne(CacheRecord::class, ['id' => 'cacheId']);
    }

    /**
     * Returns the associated element field cache records
     */
    public function getElementFieldCaches(): ActiveQueryInterface
    {
        return $this->hasMany(ElementFieldCacheRecord::class, [
            'cacheId' => 'cacheId',
            'elementId' => 'elementId',
        ]);
    }
}
