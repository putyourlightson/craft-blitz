<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\models;

use Craft;
use craft\base\Model;
use craft\behaviors\EnvAttributeParserBehavior;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\drivers\deployers\DummyDeployer;
use putyourlightson\blitz\drivers\generators\HttpGenerator;
use putyourlightson\blitz\drivers\integrations\CommerceIntegration;
use putyourlightson\blitz\drivers\integrations\FeedMeIntegration;
use putyourlightson\blitz\drivers\integrations\SeomaticIntegration;
use putyourlightson\blitz\drivers\purgers\DummyPurger;
use putyourlightson\blitz\drivers\storage\FileStorage;

class SettingsModel extends Model
{
    /**
     * @const int
     */
    public const REFRESH_MODE_EXPIRE = 0;

    /**
     * @const int
     */
    public const REFRESH_MODE_CLEAR = 1;

    /**
     * @const int
     */
    public const REFRESH_MODE_EXPIRE_AND_GENERATE = 2;

    /**
     * @const int
     */
    public const REFRESH_MODE_CLEAR_AND_GENERATE = 3;

    /**
     * @const int
     */
    public const QUERY_STRINGS_DO_NOT_CACHE_URLS = 0;

    /**
     * @const int
     */
    public const QUERY_STRINGS_CACHE_URLS_AS_UNIQUE_PAGES = 1;

    /**
     * @const int
     */
    public const QUERY_STRINGS_CACHE_URLS_AS_SAME_PAGE = 2;

    /**
     * @const int
     */
    public const OUTPUT_COMMENTS_CACHED = 2;

    /**
     * @const int
     */
    public const OUTPUT_COMMENTS_SERVED = 3;

    /**
     * @var bool With this setting enabled, Blitz will log detailed messages to `storage/logs/blitz.php`.
     */
    public bool $debug = false;

    /**
     * @var bool With this setting enabled, Blitz will provide template performance hints in a utility.
     */
    public bool $hintsEnabled = true;

    /**
     * @var bool With this setting enabled, Blitz will begin caching pages according to the included/excluded URI patterns. Disable this setting to prevent Blitz from caching any new pages.
     */
    public bool $cachingEnabled = false;

    /**
     * @var int Determines when and how the cache should be refreshed.
     *
     * - `self::REFRESH_MODE_EXPIRE`: Expire the cache, regenerate manually
     * - `self::REFRESH_MODE_CLEAR`: Clear the cache, regenerate manually or organically
     * - `self::REFRESH_MODE_EXPIRE_AND_GENERATE`: Expire the cache and regenerate in a queue job
     * - `self::REFRESH_MODE_CLEAR_AND_GENERATE`: Clear the cache and regenerate in a queue job
     */
    public int $refreshMode = self::REFRESH_MODE_CLEAR_AND_GENERATE;

    /**
     * @var array The URI patterns to include in caching. Set `siteId` to a blank string to indicate all sites.
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
    public array $includedUriPatterns = [];

    /**
     * @var array The URI patterns to exclude from caching (overrides any matching patterns to include). Set `siteId` to a blank string to indicate all sites.
     *
     * [
     *     [
     *         'siteId' => 1,
     *         'uriPattern' => 'pages/contact',
     *     ],
     * ]
     */
    public array $excludedUriPatterns = [];

    /**
     * @var string The storage type to use.
     */
    public string $cacheStorageType = FileStorage::class;

    /**
     * @var array The storage settings.
     */
    public array $cacheStorageSettings = [];

    /**
     * @var array The storage type classes to add to the plugin’s default storage types.
     */
    public array $cacheStorageTypes = [];

    /**
     * @var string The generator type to use.
     */
    public string $cacheGeneratorType = HttpGenerator::class;

    /**
     * @var array The generator settings.
     */
    public array $cacheGeneratorSettings = [];

    /**
     * @var string[] The generator type classes to add to the plugin’s default generator types.
     */
    public array $cacheGeneratorTypes = [];

    /**
     * @var array Custom site URIs to generate when either a site or the entire cache is generated.
     *
     * [
     *     [
     *         'siteId' => 1,
     *         'uri' => 'pages/custom',
     *     ],
     * ]
     *
     * @used-by getCustomSiteUris()
     */
    public array $customSiteUris = [];

    /**
     * @var string The purger type to use.
     */
    public string $cachePurgerType = DummyPurger::class;

    /**
     * @var array The purger settings.
     */
    public array $cachePurgerSettings = [];

    /**
     * @var string[] The purger type classes to add to the plugin’s default purger types.
     */
    public array $cachePurgerTypes = [];

    /**
     * @var string The deployer type to use.
     */
    public string $deployerType = DummyDeployer::class;

    /**
     * @var array The deployer settings.
     */
    public array $deployerSettings = [];

    /**
     * @var string[] The deployer type classes to add to the plugin’s default deployer types.
     */
    public array $deployerTypes = [];

    /**
     * @var bool With this setting enabled, Blitz will fetch cached includes using Server-Side Includes (SSI), which must be enabled on the server.
     */
    public bool $ssiEnabled = false;

    /**
     * @var bool With this setting enabled, Blitz will fetch cached includes using Edge-Side Includes (ESI), which must be enabled on the server.
     */
    public bool $esiEnabled = false;

    /**
     * @var int Whether URLs with query strings should be cached and how.
     *
     * - `self::QUERY_STRINGS_DO_NOT_CACHE_URLS`: Do not cache URLs with query strings
     * - `self::QUERY_STRINGS_CACHE_URLS_AS_UNIQUE_PAGES`: Cache URLs with query strings as unique pages
     * - `self::QUERY_STRINGS_CACHE_URLS_AS_SAME_PAGE`: Cache URLs with query strings as the same page
     */
    public int $queryStringCaching = self::QUERY_STRINGS_DO_NOT_CACHE_URLS;

    /**
     * @var array The query string parameters to include when determining if and how a page should be cached (regular expressions may be used).
     *
     * [
     *     [
     *         'siteId' => '',
     *         'queryStringParam' => '.*',
     *     ],
     * ]
     */
    public array $includedQueryStringParams = [
        [
            'siteId' => '',
            'queryStringParam' => '.*',
        ],
    ];

    /**
     * @var array The query string parameters to exclude when determining if and how a page should be cached (regular expressions may be used).
     *
     * [
     *     [
     *         'siteId' => '',
     *         'queryStringParam' => 'gclid',
     *     ],
     *     [
     *         'siteId' => '',
     *         'queryStringParam' => 'fbclid',
     *     ],
     * ]
     */
    public array $excludedQueryStringParams = [
        [
            'siteId' => '',
            'queryStringParam' => 'gclid',
        ],
        [
            'siteId' => '',
            'queryStringParam' => 'fbclid',
        ],
    ];

    /**
     * @var string An API key that can be used via a URL (min. 16 characters).
     */
    public string $apiKey = '';

    /**
     * @var bool Whether pages containing query string parameters should be generated.
     */
    public bool $generatePagesWithQueryStringParams = true;

    /**
     * @var bool Whether asset images should be purged when changed.
     */
    public bool $purgeAssetImagesWhenChanged = true;

    /**
     * @var bool Whether the cache should automatically be refreshed after a global set is updated.
     */
    public bool $refreshCacheAutomaticallyForGlobals = true;

    /**
     * @var bool Whether the cache should be refreshed when an element is moved within a structure.
     */
    public bool $refreshCacheWhenElementMovedInStructure = true;

    /**
     * @var bool Whether the cache should be refreshed when an element is saved but unchanged.
     */
    public bool $refreshCacheWhenElementSavedUnchanged = false;

    /**
     * @var bool Whether the cache should be refreshed when an element is saved but not live.
     */
    public bool $refreshCacheWhenElementSavedNotLive = false;

    /**
     * @var bool Whether non-HTML responses should be cached.
     */
    public bool $cacheNonHtmlResponses = false;

    /**
     * @var bool Whether elements should be tracked in the database.
     */
    public bool $trackElements = true;

    /**
     * @var bool Whether element queries should be tracked in the database.
     */
    public bool $trackElementQueries = true;

    /**
     * @var bool Whether elements should be cached in the database.
     *
     * @deprecated in 4.4.0. Use [[trackElements]] instead.
     */
    public bool $cacheElements = true;

    /**
     * @var bool Whether element queries should be cached in the database.
     *
     * @deprecated in 4.4.0. Use [[trackElementQueries]] instead.
     */
    public bool $cacheElementQueries = true;

    /**
     * @var int|null The amount of time after which the cache should expire (if not 0).
     * See [[ConfigHelper::durationInSeconds()]] for a list of supported value types.
     */
    public ?int $cacheDuration = null;

    /**
     * @var string[] Element types that should not be cached (in addition to the defaults).
     */
    public array $nonCacheableElementTypes = [];

    /**
     * @var string[] Source ID attributes for element types (in addition to the defaults).
     */
    public array $sourceIdAttributes = [];

    /**
     * @var string[] Live statuses for element types (in addition to the defaults).
     */
    public array $liveStatuses = [];

    /**
     * @var string[] The integrations to initialise.
     */
    public array $integrations = [
        CommerceIntegration::class,
        FeedMeIntegration::class,
        SeomaticIntegration::class,
    ];

    /**
     * @var string The value to send in the cache control header.
     *
     * https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Cache-Control
     * https://developers.cloudflare.com/cache/about/cache-control/
     */
    public string $cacheControlHeader = 'public, s-maxage=31536000, max-age=0';

    /**
     * @var bool Whether an `X-Powered-By: Blitz` header should be sent.
     */
    public bool $sendPoweredByHeader = true;

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
    public int|bool $outputComments = true;

    /**
     * @var int The priority to give the refresh cache job (the lower the number, the higher the priority).
     */
    public int $refreshCacheJobPriority = 10;

    /**
     * @var int The priority to give driver jobs (the lower the number, the higher the priority).
     */
    public int $driverJobPriority = 100;

    /**
     * @var int The time to reserve for queue jobs in seconds.
     */
    public int $queueJobTtr = 300;

    /**
     * @var int The maximum number of times to attempt retrying a failed queue job.
     */
    public int $maxRetryAttempts = 10;

    /**
     * @var int The time in seconds to wait for mutex locks to be released.
     */
    public int $mutexTimeout = 1;

    /**
     * @var int The maximum length of URIs that may be cached. Increasing this value requires manually updating the limit in the `uri` column of the `blitz_caches` database table. Note that the prefix length limit is 3072 bytes for InnoDB tables that use the DYNAMIC or COMPRESSED row format. Assuming a `utf8mb4` character set and a maximum of 4 bytes for each character, this is 768 characters.
     * https://dev.mysql.com/doc/refman/8.0/en/column-indexes.html#column-indexes-prefix
     *
     * Warning: if using the File Storage driver, this value should not exceed 255 unless using a file system that supports longer filenames.
     * https://en.wikipedia.org/wiki/Comparison_of_file_systems#Limits
     */
    public int $maxUriLength = 255;

    /**
     * @var int The maximum length of SSI values that may be cached.
     * https://nginx.org/en/docs/http/ngx_http_ssi_module.html#ssi_value_length
     */
    public int $maxSsiValueLength = 256;

    /**
     * @var int The maximum length of ESI values that may be cached.
     * https://nginx.org/en/docs/http/ngx_http_ssi_module.html#ssi_value_length
     */
    public int $maxEsiValueLength = 256;

    /**
     * @var array The paths to executable shell commands.
     *
     * [
     *     'git' => '/usr/bin/git',
     * ]
     */
    public array $commands = [];

    /**
     * @var string The name of the JavaScript event that will trigger a script inject.
     */
    public string $injectScriptEvent = 'DOMContentLoaded';

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
     * Returns whether the cache should be cleared on refresh.
     *
     * @since 4.0.0
     */
    public function clearOnRefresh(bool $force = false): bool
    {
        if ($force) {
            return true;
        }

        return $this->refreshMode == self::REFRESH_MODE_CLEAR
            || $this->refreshMode == self::REFRESH_MODE_CLEAR_AND_GENERATE;
    }

    /**
     * Returns whether the cache should be generated on refresh.
     *
     * @since 4.0.0
     */
    public function generateOnRefresh(bool $force = false): bool
    {
        if ($force) {
            return true;
        }

        if (!$this->cachingEnabled) {
            return false;
        }

        return $this->refreshMode == self::REFRESH_MODE_EXPIRE_AND_GENERATE
            || $this->refreshMode == self::REFRESH_MODE_CLEAR_AND_GENERATE;
    }

    /**
     * Returns whether the cache should be purged after being generated.
     *
     * @since 4.0.0
     */
    public function purgeAfterGenerate(bool $forceClear = false, bool $forceGenerate = false): bool
    {
        return !$this->clearOnRefresh($forceClear) && $this->generateOnRefresh($forceGenerate);
    }

    /**
     * Returns whether the page should be generated based on whether a query
     * string exists in the URI.
     *
     * @since 4.4.0
     */
    public function generatePageBasedOnQueryString(string $uri): bool
    {
        if ($this->generatePagesWithQueryStringParams === true) {
            return true;
        }

        // Cached includes are always allowed
        if (Blitz::$plugin->cacheRequest->getIsCachedInclude($uri)) {
            return true;
        }

        return !str_contains($uri, '?');
    }

    /**
     * Returns whether the cache should be purged after being generated.
     *
     * @since 4.4.0
     */
    public function purgeAssetImages(): bool
    {
        return $this->purgeAssetImagesWhenChanged && $this->cachePurgerType !== DummyPurger::class;
    }

    /**
     * Returns the custom site URIs as site URI models.
     *
     * @return SiteUriModel[]
     * @since 4.3.0
     */
    public function getCustomSiteUris(): array
    {
        $customSiteUris = [];

        foreach ($this->customSiteUris as $customSiteUri) {
            $customSiteUris[] = new SiteUriModel($customSiteUri);
        }

        return $customSiteUris;
    }

    /**
     * @inheritdoc
     */
    protected function defineBehaviors(): array
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
    protected function defineRules(): array
    {
        return [
            [['cacheStorageType', 'cacheGeneratorType', 'queryStringCaching'], 'required'],
            [['cacheStorageType', 'cacheGeneratorType', 'cachePurgerType', 'deployerType'], 'string', 'max' => 255],
            [['refreshMode'], 'in', 'range' => [
                self::REFRESH_MODE_EXPIRE,
                self::REFRESH_MODE_CLEAR,
                self::REFRESH_MODE_EXPIRE_AND_GENERATE,
                self::REFRESH_MODE_CLEAR_AND_GENERATE,
            ]],
            [['queryStringCaching'], 'in', 'range' => [
                self::QUERY_STRINGS_DO_NOT_CACHE_URLS,
                self::QUERY_STRINGS_CACHE_URLS_AS_UNIQUE_PAGES,
                self::QUERY_STRINGS_CACHE_URLS_AS_SAME_PAGE,
            ]],
            [['apiKey'], 'string', 'length' => [16]],
            [['cachingEnabled', 'cacheElements', 'cacheElementQueries'], 'boolean'],
        ];
    }
}
