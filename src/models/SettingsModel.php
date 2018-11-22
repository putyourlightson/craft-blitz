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
     * @var bool
     */
    public $warmCacheAutomatically = false;

    /**
     * @var int
     */
    public $queryStringCaching = 0;

    /**
     * @var string
     */
    public $cacheFolderPath = 'cache/blitz';

    /**
     * @var int
     */
    public $concurrency = 1;

    /**
     * @var mixed
     */
    public $includedUriPatterns = [];

    /**
     * @var mixed
     */
    public $excludedUriPatterns = [];

    /**
     * @var bool
     */
    public $elementCachingDisabled = false;

    /**
     * @var bool
     */
    public $elementQueryCachingDisabled = false;

    // Public Methods
    // =========================================================================

    /**
     * @return array
     */
    public function rules(): array
    {
        return [
            [['concurrency'], 'integer', 'min' => 1],
        ];
    }

}
