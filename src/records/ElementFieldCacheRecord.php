<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\records;

use craft\db\ActiveRecord;

/**
 * @property int $cacheId
 * @property int $elementId
 * @property string $fieldInstanceUid
 *
 * @since 4.4.0
 */
class ElementFieldCacheRecord extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%blitz_elementfieldcaches}}';
    }
}
