<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\records;

use craft\db\ActiveRecord;
use yii\db\ActiveQueryInterface;

/**
 * @property int $id
 * @property int $cacheId
 * @property string $type
 * @property string $query
 * @property CacheRecord $cache
 */
class ElementQueryCacheRecord extends ActiveRecord
{
    // Public Static Methods
    // =========================================================================

     /**
     * @inheritdoc
     *
     * @return string
     */
    public static function tableName(): string
    {
        return '{{%blitz_elementquerycaches}}';
    }

    /**
     * Returns the associated cache
     *
     * @return ActiveQueryInterface
     */
    public function getCache(): ActiveQueryInterface
    {
        return $this->hasOne(CacheRecord::class, ['id' => 'cacheId']);
    }
}
