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
 * @property ElementQueryCacheRecord[] $elementQueryCache
 */
class ElementQueryRecord extends ActiveRecord
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
        return '{{%blitz_elementqueries}}';
    }

    /**
     * Returns the associated element query cache
     *
     * @return ActiveQueryInterface
     */
    public function getElementQueryCache(): ActiveQueryInterface
    {
        return $this->hasMany(ElementQueryCacheRecord::class, ['queryId' => 'id']);
    }
}
