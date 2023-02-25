<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\records;

use craft\db\ActiveQuery;
use craft\db\ActiveRecord;
use DateTime;

/**
 * @property int $id
 * @property int $siteId
 * @property string $uri
 * @property int|null $paginate
 * @property DateTime|null $expiryDate
 * @property-read ElementCacheRecord[] $elements
 */
class CacheRecord extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%blitz_caches}}';
    }

    /**
     * Returns the associated elements
     */
    public function getElements(): ActiveQuery
    {
        return $this->hasMany(ElementCacheRecord::class, ['cacheId' => 'id']);
    }
}
