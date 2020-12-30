<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\records;

use craft\db\ActiveRecord;
use DateTime;
use yii\db\ActiveQueryInterface;

/**
 * @property int $id
 * @property int $siteId
 * @property string $uri
 * @property DateTime|null $expiryDate
 * @property ActiveQueryInterface $elements
 */
class CacheRecord extends ActiveRecord
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
        return '{{%blitz_caches}}';
    }

    /**
     * Returns the associated elements
     *
     * @return ActiveQueryInterface
     */
    public function getElements(): ActiveQueryInterface
    {
        return $this->hasMany(ElementCacheRecord::class, ['cacheId' => 'id']);
    }
}
