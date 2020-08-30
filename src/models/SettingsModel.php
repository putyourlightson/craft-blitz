<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\models;

use Craft;
use craft\base\Model;
use craft\behaviors\EnvAttributeParserBehavior;
use putyourlightson\blitz\drivers\integrations\FeedMeIntegration;
use putyourlightson\blitz\drivers\integrations\SeomaticIntegration;
use putyourlightson\blitz\drivers\deployers\DummyDeployer;
use putyourlightson\blitz\drivers\storage\FileStorage;
use putyourlightson\blitz\drivers\purgers\DummyPurger;
use putyourlightson\blitz\drivers\warmers\GuzzleWarmer;

class SettingsModel extends Model
{
    // Constants
    // =========================================================================

    /**
     * @const int
     */
    const QUERY_STRINGS_DO_NOT_CACHE_URLS = 0;

    /**
     * @const int
     */
    const QUERY_STRINGS_CACHE_URLS_AS_UNIQUE_PAGES = 1;

    /**
     * @const int
     */
    const QUERY_STRINGS_CACHE_URLS_AS_SAME_PAGE = 2;

    /**
     * @const int
     */
    const OUTPUT_COMMENTS_CACHED = 2;

    /**
     * @const int
     */
    const OUTPUT_COMMENTS_SERVED = 3;

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
     *         'uriPattern' => 'pages/.*',
     *     ],
     *     [
     *         'siteId' => '',
     *         'uriPattern' => 'articles/.*',
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
     *         'uriPattern' => 'pages/contact',
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
     * @var array Custom site URIs to warm when either a site or the entire cache is warmed.
     *
     * [
     *     [
     *         'siteId' => 1,
     *         'uri' => 'pages/custom',
     *     ],
     * ]
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
     * @var string[] The purger type classes to add to the plugin’s default purger types.
     */
    public $cachePurgerTypes = [];

    /**
     * @var string The deployer type to use.
     */
    public $deployerType = DummyDeployer::class;

    /**
     * @var array The deployer settings.
     */
    public $deployerSettings = [];

    /**
     * @var string[] The deployer type classes to add to the plugin’s default deployer types.
     */
    public $deployerTypes = [];

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
     * @var bool Whether the cache should be refreshed when an element is saved but unchanged.
     */
    public $refreshCacheWhenElementSavedUnchanged = false;

    /**
     * @var bool Whether the cache should be refreshed when an element is saved but not live.
     */
    public $refreshCacheWhenElementSavedNotLive = false;

    /**
     * @var int Whether URLs with query strings should cached and how.
     *
     * Can be set to any of the following:
     *
     * - `self::QUERY_STRINGS_DO_NOT_CACHE_URLS`: Do not cache URLs with query strings
     * - `self::QUERY_STRINGS_CACHE_URLS_AS_UNIQUE_PAGES`: Cache URLs with query strings as unique pages
     * - `self::QUERY_STRINGS_CACHE_URLS_AS_SAME_PAGE`: Cache URLs with query strings as the same page
     */
    public $queryStringCaching = self::QUERY_STRINGS_DO_NOT_CACHE_URLS;

    /**
     * @var string[] The query string parameters to exclude when determining if and how a page should be cached.
     */
    public $excludedQueryStringParams = ['gclid', 'fbclid'];

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
     * @var int|null The amount of time after which the cache should expire (if not 0).
     * See [[ConfigHelper::durationInSeconds()]] for a list of supported value types.
     */
    public $cacheDuration;

    /**
     * @var string[] Element types that should not be cached (in addition to the defaults).
     */
    public $nonCacheableElementTypes = [];

    /**
     * @var string[] Source ID attributes for element types (in addition to the defaults).
     */
    public $sourceIdAttributes = [];

    /**
     * @var string[] Live statuses for element types (in addition to the defaults).
     */
    public $liveStatuses = [];

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
     * @var int|bool Whether the "cached on" and "served by" timestamp comments should be appended to the cached output.
     *
     * Can be set to any of the following:
     *
     * - `false`: Do not append any comments
     * - `true`: Append all comments
     * - `self::OUTPUT_COMMENTS_CACHED`: Append "cached on" comment only
     * - `self::OUTPUT_COMMENTS_SERVED`: Append "served by" comment only
     */
    public $outputComments = true;

    /**
     * @var int The priority to give the refresh cache job (the lower the number, the higher the priority).
     */
    public $refreshCacheJobPriority = 10;

    /**
     * @var int The priority to give driver jobs (the lower the number, the higher the priority).
     */
    public $driverJobPriority = 100;

    /**
     * @var int The time in seconds to wait for mutex locks to be released.
     */
    public $mutexTimeout = 1;

    /**
     * @var array The paths to executable shell commands.
     *
     * [
     *     'git' => '/usr/bin/git',
     * ]
     */
    public $commands = [];

    /**
     * @var string The name of the JavaScript event that will trigger a script inject.
     */
    public $injectScriptEvent = 'DOMContentLoaded';

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
            [['queryStringCaching'], 'in', 'range' => [
                self::QUERY_STRINGS_DO_NOT_CACHE_URLS,
                self::QUERY_STRINGS_CACHE_URLS_AS_UNIQUE_PAGES,
                self::QUERY_STRINGS_CACHE_URLS_AS_SAME_PAGE,
            ]],
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

    /**
     * Returns custom site URIs as an array
     */
    public function getCustomSiteUris()
    {
        return is_array($this->customSiteUris) ? $this->customSiteUris : [];
    }
}
