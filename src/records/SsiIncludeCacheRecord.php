<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\records;

use craft\db\ActiveRecord;
use yii\db\ActiveQueryInterface;

/**
 * @property int $cacheId
 * @property int $ssiIncludeId
 * @property SsiIncludeRecord[] $ssiInclude
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
     * Returns the associated SSI include
     */
    public function getSsiInclude(): ActiveQueryInterface
    {
        return $this->hasOne(SsiIncludeRecord::class, ['id' => 'ssiIncludeId']);
    }
}
