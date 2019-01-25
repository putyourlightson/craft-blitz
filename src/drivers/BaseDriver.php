<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\drivers;

use craft\base\SavableComponent;

/**
 * @property string $utilityHtml
 */
abstract class BaseDriver extends SavableComponent implements DriverInterface
{
    /**
     * @inheritdoc
     */
    public function getUtilityHtml(): string
    {
        return '';
    }
}