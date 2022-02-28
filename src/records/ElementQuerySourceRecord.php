<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\records;

use craft\db\ActiveRecord;
use yii\db\ActiveQueryInterface;

/**
 * @property int $id
 * @property int $sourceId
 * @property int $queryId
 * @property ElementQueryRecord $elementQuery
 */
class ElementQuerySourceRecord extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%blitz_elementquerysources}}';
    }

    /**
     * Returns the associated element query
     */
    public function getElementQuery(): ActiveQueryInterface
    {
        return $this->hasOne(ElementQueryRecord::class, ['id' => 'queryId']);
    }
}
