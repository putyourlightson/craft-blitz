<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\models;

use craft\base\Model;
use putyourlightson\blitz\drivers\FileDriver;
use putyourlightson\blitz\purgers\DummyPurger;

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
    public $warmCacheAutomatically = true;

    /**
     * @var int
     */
    public $queryStringCaching = 0;

    /**
     * @var int
     */
    public $concurrency = 5;

    /**
     * @var string
     */
    public $driverType = FileDriver::class;

    /**
     * @var array|null
     */
    public $driverSettings;

    /**
     * @var array
     */
    public $driverTypes = [];

    /**
     * @var string
     */
    public $purgerType = DummyPurger::class;

    /**
     * @var array|null
     */
    public $purgerSettings;

    /**
     * @var array
     */
    public $purgerTypes = [];

    /**
     * @var array
     */
    public $includedUriPatterns = [];

    /**
     * @var array
     */
    public $excludedUriPatterns = [];

    /**
     * @var bool
     */
    public $cacheElements = true;

    /**
     * @var bool
     */
    public $cacheElementQueries = true;

    /**
     * @var string[]
     */
    public $nonCacheableElementTypes = [
        'craft\elements\GlobalSet',
        'craft\elements\MatrixBlock',
    ];

    /**
     * @var bool
     */
    public $sendPoweredByHeader = true;

    /**
     * @var bool
     */
    public $warmCacheAutomaticallyForGlobals = true;

    // Public Methods
    // =========================================================================

    /**
     * @return array
     */
    public function rules(): array
    {
        return [
            [['queryStringCaching', 'concurrency', 'driverType'], 'required'],
            [['queryStringCaching'], 'integer', 'min' => 0, 'max' => 2],
            [['concurrency'], 'integer', 'min' => 1],
        ];
    }

}
