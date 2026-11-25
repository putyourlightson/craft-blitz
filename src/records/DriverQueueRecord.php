<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\records;

use craft\db\ActiveRecord;

/**
 * @property int $id
 * @property int $siteUriId
 * @property string $driverId
 * @property string $driverMethod
 * @property int $priority
 * @property string $revision
 */
class DriverQueueRecord extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%blitz_driverqueue}}';
    }
}
