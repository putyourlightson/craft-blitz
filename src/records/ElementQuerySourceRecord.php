<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\records;

use craft\db\ActiveQuery;
use craft\db\ActiveRecord;

/**
 * @property int $id
 * @property int $sourceId
 * @property int $queryId
 * @property-read ElementQueryRecord $elementQuery
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
    public function getElementQuery(): ActiveQuery
    {
        return $this->hasOne(ElementQueryRecord::class, ['id' => 'queryId']);
    }
}
