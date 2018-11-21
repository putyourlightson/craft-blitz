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
     * @var bool
     */
    public $queryStringCachingEnabled = false;

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
    public $elementCachingDisabled = true;

    /**
     * @var bool
     */
    public $elementQueryCachingDisabled = true;

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
