<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\records;

use craft\db\ActiveQuery;
use craft\db\ActiveRecord;

/**
 * @property int $queryId
 * @property int $siteId
 * @property-read ElementQueryRecord $elementQuery
 *
 * @since 5.13.0
 */
class ElementQuerySiteRecord extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%blitz_elementquerysites}}';
    }

    /**
     * Returns the associated element query
     */
    public function getElementQuery(): ActiveQuery
    {
        return $this->hasOne(ElementQueryRecord::class, ['id' => 'queryId']);
    }
}
