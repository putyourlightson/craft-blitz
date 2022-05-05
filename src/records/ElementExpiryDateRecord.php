<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\records;

use craft\db\ActiveRecord;
use DateTime;

/**
 * @property int $elementId
 * @property DateTime $expiryDate
 */
class ElementExpiryDateRecord extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%blitz_elementexpirydates}}';
    }
}
