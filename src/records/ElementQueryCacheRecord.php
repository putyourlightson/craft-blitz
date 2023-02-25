<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\records;

use craft\db\ActiveQuery;
use craft\db\ActiveRecord;

/**
 * @property int $cacheId
 * @property int $queryId
 * @property-read CacheRecord $cache
 * @property-read ElementQueryRecord $elementQuery
 */
class ElementQueryCacheRecord extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%blitz_elementquerycaches}}';
    }

    /**
     * Returns the associated cache
     */
    public function getCache(): ActiveQuery
    {
        return $this->hasOne(CacheRecord::class, ['id' => 'cacheId']);
    }

    /**
     * Returns the associated element query
     */
    public function getElementQuery(): ActiveQuery
    {
        return $this->hasOne(ElementQueryRecord::class, ['id' => 'queryId']);
    }
}
