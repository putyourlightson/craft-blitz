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
use putyourlightson\blitz\drivers\warmers\GuzzleWarmer;

class SettingsModel extends Model
{
    // Public Properties
    // =========================================================================

    /**
     * With this setting enabled, Blitz will log detailed messages to `storage/logs/blitz.php`.
     *
     * @var bool
     */
    public $debug = false;

    /**
     * With this setting enabled, Blitz will begin caching pages according to the included/excluded URI patterns. Disable this setting to prevent Blitz from caching any new pages.
     *
     * @var bool
     */
    public $cachingEnabled = false;

    /**
     * The URI patterns to include in caching. Set `siteId` to a blank string to indicate all sites.
     * [
     *     [
     *         'siteId' => 1,
     *         'uri' => 'pages/.*',
     *     ],
     *     [
     *         'siteId' => '',
     *         'uri' => 'articles/.*',
     *     ],
     * ]
     *
     * @var array|string
     */
    public $includedUriPatterns = [];

    /**
     * The URI patterns to exclude from caching (overrides any matching patterns to include). Set `siteId` to a blank string to indicate all sites.
     * [
     *     [
     *         'siteId' => 1,
     *         'uri' => 'pages/contact',
     *     ],
     * ]
     *
     * @var array|string
     */
    public $excludedUriPatterns = [];

    /**
     * The storage type to use.
     *
     * @var string
     */
    public $cacheStorageType = FileStorage::class;

    /**
     * The storage settings.
     *
     * @var array
     */
    public $cacheStorageSettings = [];

    /**
     * The storage type classes to add to the plugin’s default storage types.
     *
     * @var array
     */
    public $cacheStorageTypes = [];

    /**
     * The warmer type to use.
     *
     * @var string
     */
    public $cacheWarmerType = GuzzleWarmer::class;

    /**
     * The warmer settings.
     *
     * @var array
     */
    public $cacheWarmerSettings = [];

    /**
     * The warmer type classes to add to the plugin’s default warmer types.
     *
     * @var string[]
     */
    public $cacheWarmerTypes = [];

    /**
     * Custom site URIs to warm when either a site or the entire cache is warmed.
     *
     * @var array|string
     */
    public $customSiteUris = [];

    /**
     * The purger type to use.
     *
     * @var string
     */
    public $cachePurgerType = DummyPurger::class;

    /**
     * The purger settings.
     *
     * @var array
     */
    public $cachePurgerSettings = [];

    /**
     * The purger type classes that should be available.
     *
     * @var string[]
     */
    public $cachePurgerTypes = [CloudflarePurger::class];

    /**
     * The deployer type to use.
     *
     * @var string
     */
    public $deployerType = DummyDeployer::class;

    /**
     * The deployer settings.
     *
     * @var array
     */
    public $deployerSettings = [];

    /**
     * The deployer type classes that should be available.
     *
     * @var string[]
     */
    public $deployerTypes = [GitDeployer::class];

    /**
     * Whether the cache should automatically be cleared when elements are updated.
     *
     * @var bool
     */
    public $clearCacheAutomatically = true;

    /**
     * Whether the cache should automatically be warmed after clearing.
     *
     * @var bool
     */
    public $warmCacheAutomatically = true;

    /**
     * Whether the cache should automatically be refreshed after a global set is updated.
     *
     * @var bool
     */
    public $refreshCacheAutomaticallyForGlobals = true;

    /**
     * Whether URLs with query strings should cached and how.
     * 0: Do not cache URLs with query strings
     * 1: Cache URLs with query strings as unique pages
     * 2: Cache URLs with query strings as the same page
     *
     * @var int
     */
    public $queryStringCaching = 0;

    /**
     * An API key that can be used to clear, flush, warm, or refresh expired cache through a URL (min. 16 characters).
     *
     * @var string
     */
    public $apiKey = '';

    /**
     * Whether elements should be cached in the database.
     *
     * @var bool
     */
    public $cacheElements = true;

    /**
     * Whether element queries should be cached in the database.
     *
     * @var bool
     */
    public $cacheElementQueries = true;

    /**
     * The amount of time after which the cache should expire (if not 0). See [[ConfigHelper::durationInSeconds()]] for a list of supported value types.
     *
     * @var int|null
     */
    public $cacheDuration;

    /**
     * Element types that should not be cached.
     *
     * @var string[]
     */
    public $nonCacheableElementTypes = [];

    /**
     * The integrations to initialise.
     *
     * @var string[]
     */
    public $integrations = [
        FeedMeIntegration::class,
        SeomaticIntegration::class,
    ];

    /**
     * The value to send in the cache control header.
     *
     * @var string
     */
    public $cacheControlHeader = 'public, s-maxage=31536000, max-age=0';

    /**
     * Whether an `X-Powered-By: Blitz` header should be sent.
     *
     * @var bool
     */
    public $sendPoweredByHeader = true;

    /**
     * Whether the timestamp and served by comments should be appended to the cached output.
     *
     * @var bool
     */
    public $outputComments = true;

    /**
     * The priority to give the refresh cache job (the lower number the number, the higher the priority). Set to `null` to inherit the default priority.
     *
     * @var int|null
     */
    public $refreshCacheJobPriority = 10;

    /**
     * The priority to give driver jobs (the lower number the number, the higher the priority). Set to `null` to inherit the default priority.
     *
     * @var int|null
     */
    public $driverJobPriority = 100;

    /**
     * The time in seconds to wait for mutex locks to be released.
     *
     * @var int|null
     */
    public $mutexTimeout = 1;

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
            [['cacheStorageType', 'cacheWarmerType', 'queryStringCaching'], 'required'],
            [['cacheStorageType', 'cacheWarmerType', 'cachePurgerType', 'deployerType'], 'string', 'max' => 255],
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
