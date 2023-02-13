<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\records;

use craft\db\ActiveRecord;

/**
 * @property int $cacheId
 * @property int $elementId
 * @property int $fieldId
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
