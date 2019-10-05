<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\models;

use Craft;
use craft\base\Model;
use craft\behaviors\EnvAttributeParserBehavior;
use putyourlightson\blitz\drivers\deployers\GitDeployer;
use putyourlightson\blitz\drivers\integrations\FeedMeIntegration;
use putyourlightson\blitz\drivers\integrations\SeomaticIntegration;
use putyourlightson\blitz\drivers\purgers\CloudflarePurger;
use putyourlightson\blitz\drivers\deployers\DummyDeployer;
use putyourlightson\blitz\drivers\storage\FileStorage;
use putyourlightson\blitz\drivers\purgers\DummyPurger;
use putyourlightson\blitz\drivers\warmers\LocalWarmer;

class SettingsModel extends Model
{
    // Public Properties
    // =========================================================================

    /**
     * @var bool
     */
    public $cachingEnabled = false;

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
    public $cacheStorageType = FileStorage::class;

    /**
     * @var array
     */
    public $cacheStorageSettings = [];

    /**
     * @var array
     */
    public $cacheStorageTypes = [];

    /**
     * @var string
     */
    public $cacheWarmerType = LocalWarmer::class;

    /**
     * @var array
     */
    public $cacheWarmerSettings = [];

    /**
     * @var string[]
     */
    public $cacheWarmerTypes = [];

    /**
     * @var array
     */
    public $customSiteUris = [];

    /**
     * @var string
     */
    public $purgerType = DummyPurger::class;

    /**
     * @var array
     */
    public $purgerSettings = [];

    /**
     * @var string[]
     */
    public $purgerTypes = [
        CloudflarePurger::class,
    ];

    /**
     * @var string
     */
    public $deployerType = DummyDeployer::class;

    /**
     * @var array
     */
    public $deployerSettings = [];

    /**
     * @var string[]
     */
    public $deployerTypes = [
        GitDeployer::class,
    ];

    /**
     * @var bool
     */
    public $clearCacheAutomatically = true;

    /**
     * @var bool
     */
    public $clearCacheAutomaticallyForGlobals = true;

    /**
     * @var bool
     */
    public $warmCacheAutomatically = true;

    /**
     * @var bool
     */
    public $warmCacheAutomaticallyForGlobals = true;

    /**
     * @var int
     */
    public $queryStringCaching = 0;

    /**
     * @var string
     */
    public $apiKey = '';

    /**
     * @var bool
     */
    public $cacheElements = true;

    /**
     * @var bool
     */
    public $cacheElementQueries = true;

    /**
     * @var int|null
     */
    public $cacheDuration;

    /**
     * @var string[]
     */
    public $nonCacheableElementTypes = [];

    /**
     * @var string[]
     */
    public $integrations = [
        FeedMeIntegration::class,
        SeomaticIntegration::class,
    ];

    /**
     * @var string
     */
    public $cacheControlHeader = 'public, s-maxage=0';

    /**
     * @var bool
     */
    public $sendPoweredByHeader = true;

    /**
     * @var bool
     */
    public $outputComments = true;

    /**
     * @var int|null
     */
    public $refreshCacheJobPriority = 10;

    /**
     * @var int|null
     */
    public $driverJobPriority = 100;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function behaviors(): array
    {
        return [
            'parser' => [
                'class' => EnvAttributeParserBehavior::class,
                'attributes' => ['apiKey'],
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            [['cacheStorageType', 'queryStringCaching'], 'required'],
            [['cacheStorageType', 'cacheWarmerType', 'purgerType', 'deployerType'], 'string', 'max' => 255],
            [['queryStringCaching'], 'integer', 'min' => 0, 'max' => 2],
            [['apiKey'], 'string', 'length' => [16]],
            [['cachingEnabled', 'cacheElements', 'cacheElementQueries'], 'boolean'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels(): array
    {
        $labels = parent::attributeLabels();

        // Set the field labels
        $labels['apiKey'] = Craft::t('blitz', 'API Key');

        return $labels;
    }
}
