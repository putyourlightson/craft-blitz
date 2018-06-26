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
     * @var mixed
     */
    public $includeUriPatterns = [];

    /**
     * @var mixed
     */
    public $excludeUriPatterns = [];

    /**
     * @var string
     */
    public $cacheFolderPath;

}
