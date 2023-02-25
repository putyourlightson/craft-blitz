<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\records;

use craft\db\ActiveQuery;
use craft\db\ActiveRecord;

/**
 * @property int $queryId
 * @property string $attribute
 * @property-read ElementQueryRecord $elementQuery
 *
 * @since 4.4.0
 */
class ElementQueryAttributeRecord extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%blitz_elementqueryattributes}}';
    }

    /**
     * Returns the associated element query
     */
    public function getElementQuery(): ActiveQuery
    {
        return $this->hasOne(ElementQueryRecord::class, ['id' => 'queryId']);
    }
}
