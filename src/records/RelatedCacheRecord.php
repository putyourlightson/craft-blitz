<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\records;

use craft\db\ActiveRecord;

/**
 * @property int $cacheId
 * @property int $relatedCacheId
 */
class RelatedCacheRecord extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%blitz_relatedcaches}}';
    }
}
