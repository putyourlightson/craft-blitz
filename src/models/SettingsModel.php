<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\models;

use craft\base\Model;

class SettingsModel extends Model
{
    // Public Properties
    // =========================================================================

    /**
     * @var bool
     */
    public $cachingEnabled = false;

    /**
     * @var string
     */
    public $cacheFolderPath = '';

    /**
     * @var mixed
     */
    public $includedUriPatterns = [];

    /**
     * @var mixed
     */
    public $excludedUriPatterns = [];
}
