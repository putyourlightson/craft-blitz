<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\records;

use craft\db\ActiveQuery;
use craft\db\ActiveRecord;

/**
 * @property int $id
 * @property int $index [bigint(20)]
 * @property int $siteId
 * @property string $template
 * @property string $params
 * @property-read SsiIncludeCacheRecord[] $ssiIncludeCaches
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
    public function getSsiIncludeCaches(): ActiveQuery
    {
        return $this->hasMany(SsiIncludeCacheRecord::class, ['includeId' => 'id']);
    }
}
