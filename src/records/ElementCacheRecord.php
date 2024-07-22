<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\records;

use craft\db\ActiveQuery;
use craft\db\ActiveRecord;
use craft\records\Element;
use craft\records\Element_SiteSettings;

/**
 * @property int $cacheId
 * @property int $elementId
 * @property-read CacheRecord $cache
 * @property-read ElementFieldCacheRecord[] $elementFieldCaches
 */
class ElementCacheRecord extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%blitz_elementcaches}}';
    }

    /**
     * Returns the associated cache
     */
    public function getCache(): ActiveQuery
    {
        return $this->hasOne(CacheRecord::class, ['id' => 'cacheId']);
    }

    /**
     * Returns the associated element
     */
    public function getElement(): ActiveQuery
    {
        return $this->hasOne(Element::class, ['id' => 'elementId']);
    }

    /**
     * Returns the associated element site
     */
    public function getElementSite(): ActiveQuery
    {
        return $this->hasOne(Element_SiteSettings::class, ['id' => 'elementId']);
    }

    /**
     * Returns the associated element field cache records
     */
    public function getElementFieldCaches(): ActiveQuery
    {
        return $this->hasMany(ElementFieldCacheRecord::class, [
            'cacheId' => 'cacheId',
            'elementId' => 'elementId',
        ]);
    }
}
