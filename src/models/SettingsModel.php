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
     * @var bool With this setting enabled, Blitz will log detailed messages to `storage/logs/blitz.php`.
     */
    public $debug = false;

    /**
     * @var bool With this setting enabled, Blitz will begin caching pages according to the included/excluded URI patterns. Disable this setting to prevent Blitz from caching any new pages.
     */
    public $cachingEnabled = false;

    /**
     * @var array|string The URI patterns to include in caching. Set `siteId` to a blank string to indicate all sites.
     *
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
     */
    public $includedUriPatterns = [];

    /**
     * @var array|string The URI patterns to exclude from caching (overrides any matching patterns to include). Set `siteId` to a blank string to indicate all sites.
     *
     * [
     *     [
     *         'siteId' => 1,
     *         'uri' => 'pages/contact',
     *     ],
     * ]
     */
    public $excludedUriPatterns = [];

    /**
     * @var string The storage type to use.
     */
    public $cacheStorageType = FileStorage::class;

    /**
     * @var array The storage settings.
     */
    public $cacheStorageSettings = [];

    /**
     * @var array The storage type classes to add to the plugin’s default storage types.
     */
    public $cacheStorageTypes = [];

    /**
     * @var string The warmer type to use.
     */
    public $cacheWarmerType = GuzzleWarmer::class;

    /**
     * @var array The warmer settings.
     */
    public $cacheWarmerSettings = [];

    /**
     * @var string[] The warmer type classes to add to the plugin’s default warmer types.
     */
    public $cacheWarmerTypes = [];

    /**
     * @var array|string Custom site URIs to warm when either a site or the entire cache is warmed.
     */
    public $customSiteUris = [];

    /**
     * @var string The purger type to use.
     */
    public $cachePurgerType = DummyPurger::class;

    /**
     * @var array The purger settings.
     */
    public $cachePurgerSettings = [];

    /**
     * @var string[] The purger type classes that should be available.
     */
    public $cachePurgerTypes = [CloudflarePurger::class];

    /**
     * @var string The deployer type to use.
     */
    public $deployerType = DummyDeployer::class;

    /**
     * @var array The deployer settings.
     */
    public $deployerSettings = [];

    /**
     * @var string[] The deployer type classes that should be available.
     */
    public $deployerTypes = [GitDeployer::class];

    /**
     * @var bool Whether the cache should automatically be cleared when elements are updated.
     */
    public $clearCacheAutomatically = true;

    /**
     * @var bool Whether the cache should automatically be warmed after clearing.
     */
    public $warmCacheAutomatically = true;

    /**
     * @var bool Whether the cache should automatically be refreshed after a global set is updated.
     */
    public $refreshCacheAutomaticallyForGlobals = true;

    /**
     * @var int Whether URLs with query strings should cached and how.
     *
     * 0: Do not cache URLs with query strings
     * 1: Cache URLs with query strings as unique pages
     * 2: Cache URLs with query strings as the same page
     */
    public $queryStringCaching = 0;

    /**
     * @var string An API key that can be used to clear, flush, warm, or refresh expired cache through a URL (min. 16 characters).
     */
    public $apiKey = '';

    /**
     * @var bool Whether elements should be cached in the database.
     */
    public $cacheElements = true;

    /**
     * @var bool Whether element queries should be cached in the database.
     */
    public $cacheElementQueries = true;

    /**
     * @var int|null The amount of time after which the cache should expire (if not 0). See [[ConfigHelper::durationInSeconds()]] for a list of supported value types.
     */
    public $cacheDuration;

    /**
     * @var string[] Element types that should not be cached.
     */
    public $nonCacheableElementTypes = [];

    /**
     * @var string[] The integrations to initialise.
     */
    public $integrations = [
        FeedMeIntegration::class,
        SeomaticIntegration::class,
    ];

    /**
     * @var string The value to send in the cache control header.
     */
    public $cacheControlHeader = 'public, s-maxage=31536000, max-age=0';

    /**
     * @var bool Whether an `X-Powered-By: Blitz` header should be sent.
     */
    public $sendPoweredByHeader = true;

    /**
     * @var bool Whether the timestamp and served by comments should be appended to the cached output.
     */
    public $outputComments = true;

    /**
     * @var int|null The priority to give the refresh cache job (the lower number the number, the higher the priority). Set to `null` to inherit the default priority.
     */
    public $refreshCacheJobPriority = 10;

    /**
     * @var int|null The priority to give driver jobs (the lower number the number, the higher the priority). Set to `null` to inherit the default priority.
     */
    public $driverJobPriority = 100;

    /**
     * @var int|null The time in seconds to wait for mutex locks to be released.
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
