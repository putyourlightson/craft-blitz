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
 * @property string $sourceId
 * @property string $params
 * @property ElementQueryCacheRecord[] $elementQueryCaches
 * @property ElementQuerySourceRecord[] $elementQuerySource
 */
class ElementQueryRecord extends ActiveRecord
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
        return '{{%blitz_elementqueries}}';
    }

    /**
     * Returns the associated element query caches
     *
     * @return ActiveQueryInterface
     */
    public function getElementQueryCaches(): ActiveQueryInterface
    {
        return $this->hasMany(ElementQueryCacheRecord::class, ['queryId' => 'id']);
    }

    /**
     * Returns the associated element query sources
     *
     * @return ActiveQueryInterface
     */
    public function getElementQuerySources(): ActiveQueryInterface
    {
        return $this->hasMany(ElementQuerySourceRecord::class, ['queryId' => 'id']);
    }
}
