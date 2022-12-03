<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\records;

use craft\db\ActiveRecord;
use yii\db\ActiveQueryInterface;

/**
 * @property int $id
 * @property string $index
 * @property string $uri
 * @property SsiIncludeCacheRecord[] $ssiIncludeCaches
 */
class SsiIncludeRecord extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%blitz_ssiincludes}}';
    }

    /**
     * Returns the associated SSI include caches
     */
    public function getSsiIncludeCaches(): ActiveQueryInterface
    {
        return $this->hasMany(SsiIncludeCacheRecord::class, ['ssiIncludeId' => 'id']);
    }
}
