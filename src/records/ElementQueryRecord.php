<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\records;

use craft\db\ActiveRecord;
use yii\db\ActiveQueryInterface;

/**
 * @property int $id
 * @property int $index
 * @property string $type
 * @property string $params
 *
 * @property-read ElementQueryCacheRecord[] $elementQueryCaches
 * @property-read ElementQuerySourceRecord[] $elementQuerySources
 * @property-read ElementQueryFieldRecord[] $elementQueryFields
 */
class ElementQueryRecord extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%blitz_elementqueries}}';
    }

    /**
     * Returns the associated element query caches
     */
    public function getElementQueryCaches(): ActiveQueryInterface
    {
        return $this->hasMany(ElementQueryCacheRecord::class, ['queryId' => 'id']);
    }

    /**
     * Returns the associated element query sources
     */
    public function getElementQuerySources(): ActiveQueryInterface
    {
        return $this->hasMany(ElementQuerySourceRecord::class, ['queryId' => 'id']);
    }

    /**
     * Returns the associated element query fields
     */
    public function getElementQueryFields(): ActiveQueryInterface
    {
        return $this->hasMany(ElementQueryFieldRecord::class, ['queryId' => 'id']);
    }
}
