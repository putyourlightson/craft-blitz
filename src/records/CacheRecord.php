<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\records;

use yii\db\ActiveRecord;

/**
 * A Yii ActiveRecord
 *
 * @property int $id
 * @property int $siteId
 * @property string $uri
 */
class CacheRecord extends ActiveRecord
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
        return '{{%blitz_caches}}';
    }
}
