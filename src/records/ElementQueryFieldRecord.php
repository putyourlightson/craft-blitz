<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\records;

use craft\db\ActiveQuery;
use craft\db\ActiveRecord;

/**
 * @property int $queryId
 * @property string $fieldInstanceUid
 * @property-read ElementQueryRecord $elementQuery
 *
 * @since 4.4.0
 */
class ElementQueryFieldRecord extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%blitz_elementqueryfields}}';
    }

    /**
     * Returns the associated element query
     */
    public function getElementQuery(): ActiveQuery
    {
        return $this->hasOne(ElementQueryRecord::class, ['id' => 'queryId']);
    }
}
