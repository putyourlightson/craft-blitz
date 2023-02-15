<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\records;

use craft\db\ActiveRecord;
use yii\db\ActiveQueryInterface;

/**
 * @property int $queryId
 * @property int $fieldId
 * @property ElementQueryRecord $elementQuery
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
    public function getElementQuery(): ActiveQueryInterface
    {
        return $this->hasOne(ElementQueryRecord::class, ['id' => 'queryId']);
    }
}
