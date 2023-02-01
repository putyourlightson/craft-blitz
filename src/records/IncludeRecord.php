<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\records;

use craft\db\ActiveRecord;
use yii\db\ActiveQueryInterface;

/**
 * @property int $id
 * @property int $siteId
 * @property string $template
 * @property string $params
 * @property SsiIncludeCacheRecord[] $ssiIncludeCaches
 */
class IncludeRecord extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%blitz_includes}}';
    }

    /**
     * Returns the associated SSI include caches
     */
    public function getSsiIncludeCaches(): ActiveQueryInterface
    {
        return $this->hasMany(SsiIncludeCacheRecord::class, ['includeId' => 'id']);
    }
}
