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
     * @var array
     */
    public $includedUriPatterns = [];

    /**
     * @var array
     */
    public $excludedUriPatterns = [];

    /**
     * @var string
     */
    public $driverType = FileDriver::class;

    /**
     * @var array
     */
    public $driverSettings = [];

    /**
     * @var array
     */
    public $driverTypes = [];

    /**
     * @var string
     */
    public $purgerType = DummyPurger::class;

    /**
     * @var array
     */
    public $purgerSettings = [];

    /**
     * @var array
     */
    public $purgerTypes = [];

    /**
     * @var int
     */
    public $queryStringCaching = 0;

    /**
     * @var int
     */
    public $concurrency = 5;

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
     * @var string
     */
    public $cacheControlHeader = 'public, s-maxage=604800';

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
