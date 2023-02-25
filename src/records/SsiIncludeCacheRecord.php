<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\records;

use craft\db\ActiveQuery;
use craft\db\ActiveRecord;

/**
 * @property int $cacheId
 * @property int $includeId
 * @property-read IncludeRecord $include
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
    public function getInclude(): ActiveQuery
    {
        return $this->hasOne(IncludeRecord::class, ['id' => 'includeId']);
    }
}
