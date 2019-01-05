<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\records;

use craft\records\Element;
use yii\db\ActiveQueryInterface;
use craft\db\ActiveRecord;

/**
 * @property int $elementId
 * @property \DateTime $expiryDate
 * @property CacheRecord[] $elementCaches
 * @property Element $element
 */
class ElementExpiryDateRecord extends ActiveRecord
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
        return '{{%blitz_elementexpirydates}}';
    }

    /**
     * Returns the associated element caches
     *
     * @return ActiveQueryInterface
     */
    public function getElementCaches(): ActiveQueryInterface
    {
        return $this->hasMany(ElementCacheRecord::class, ['elementId' => 'elementId']);
    }

    /**
     * Returns the associated element
     *
     * @return ActiveQueryInterface
     */
    public function getElement(): ActiveQueryInterface
    {
        return $this->hasOne(Element::class, ['id' => 'elementId']);
    }
}
