<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\records;

use craft\db\ActiveRecord;
use yii\db\ActiveQueryInterface;

/**
 * @property int $cacheId
 * @property int $includeId
 * @property IncludeRecord[] $include
 */
class SsiIncludeCacheRecord extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%blitz_ssiincludecaches}}';
    }

    /**
     * Returns the associated include
     */
    public function getInclude(): ActiveQueryInterface
    {
        return $this->hasOne(IncludeRecord::class, ['id' => 'includeId']);
    }
}
