<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\records;

use craft\db\ActiveRecord;
use yii\db\ActiveQuery;

/**
 * @property int $id
 * @property int $index
 * @property string $type
 * @property string $params
 * @property bool $hasSources
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
    public function getElementQueryCaches(): ActiveQuery
    {
        return $this->hasMany(ElementQueryCacheRecord::class, ['queryId' => 'id']);
    }

    /**
     * Returns the associated element query sources
     */
    public function getElementQuerySources(): ActiveQuery
    {
        return $this->hasMany(ElementQuerySourceRecord::class, ['queryId' => 'id']);
    }

    /**
     * Returns the associated element query attributes
     */
    public function getElementQueryAttributes(): ActiveQuery
    {
        return $this->hasMany(ElementQueryFieldRecord::class, ['queryId' => 'id']);
    }

    /**
     * Returns the associated element query fields
     */
    public function getElementQueryFields(): ActiveQuery
    {
        return $this->hasMany(ElementQueryFieldRecord::class, ['queryId' => 'id']);
    }
}
