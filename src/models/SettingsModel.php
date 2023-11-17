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
     * With this setting enabled, Blitz will log detailed messages to `storage/logs/blitz.php`.
     */
    public bool $debug = false;

    /**
     * With this setting enabled, Blitz will provide template performance hints in a utility.
     */
    public bool $hintsEnabled = true;

    /**
     * With this setting enabled, Blitz will begin caching pages according to the included/excluded URI patterns. Disable this setting to prevent Blitz from caching any new pages.
     */
    public bool $cachingEnabled = false;

    /**
     * Determines when and how the cache should be refreshed.
     *
     * - `self::REFRESH_MODE_CLEAR_AND_GENERATE`: Clear the cache and regenerate in a queue job
     * - `self::REFRESH_MODE_EXPIRE_AND_GENERATE`: Expire the cache and regenerate in a queue job
     * - `self::REFRESH_MODE_CLEAR`: Clear the cache, regenerate manually or organically
     * - `self::REFRESH_MODE_EXPIRE`: Expire the cache, regenerate manually or organically*
     */
    public int $refreshMode = self::REFRESH_MODE_CLEAR_AND_GENERATE;

    /**
     * The URI patterns to include in caching. Set `siteId` to a blank string to indicate all sites.
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
     * The URI patterns to exclude from caching (overrides any matching patterns to include). Set `siteId` to a blank string to indicate all sites.
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
     * The storage type to use.
     */
    public string $cacheStorageType = FileStorage::class;

    /**
     * The storage settings.
     */
    public array $cacheStorageSettings = [];

    /**
     * The storage type classes to add to the plugin’s default storage types.
     */
    public array $cacheStorageTypes = [];

    /**
     * The generator type to use.
     */
    public string $cacheGeneratorType = HttpGenerator::class;

    /**
     * The generator settings.
     */
    public array $cacheGeneratorSettings = [];

    /**
     * The generator type classes to add to the plugin’s default generator types.
     *
     * @var string[]
     */
    public array $cacheGeneratorTypes = [];

    /**
     * Custom site URIs to generate when either a site or the entire cache is generated.
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
     * The purger type to use.
     */
    public string $cachePurgerType = DummyPurger::class;

    /**
     * The purger settings.
     */
    public array $cachePurgerSettings = [];

    /**
     * The purger type classes to add to the plugin’s default purger types.
     *
     * @var string[]
     */
    public array $cachePurgerTypes = [];

    /**
     * The deployer type to use.
     */
    public string $deployerType = DummyDeployer::class;

    /**
     * The deployer settings.
     */
    public array $deployerSettings = [];

    /**
     * The deployer type classes to add to the plugin’s default deployer types.
     *
     * @var string[]
     */
    public array $deployerTypes = [];

    /**
     * With this setting enabled, Blitz will fetch cached includes using Server-Side Includes (SSI), which must be enabled on the server.
     */
    public bool $ssiEnabled = false;

    /**
     * The format to use for SSI tags, in which `{uri}` will be replaced. You can change this when using Caddy’s `httpInclude` template function, for example.
     * https://caddyserver.com/docs/modules/http.handlers.templates#httpinclude
     */
    public string $ssiTagFormat = '<!--#include virtual="{uri}" -->';

    /**
     * With this setting enabled, Blitz will fetch cached includes using Edge-Side Includes (ESI), which must be enabled on the server.
     */
    public bool $esiEnabled = false;

    /**
     * Whether URLs with query strings should be cached and how.
     *
     * - `self::QUERY_STRINGS_DO_NOT_CACHE_URLS`: Do not cache URLs with query strings
     * - `self::QUERY_STRINGS_CACHE_URLS_AS_UNIQUE_PAGES`: Cache URLs with query strings as unique pages
     * - `self::QUERY_STRINGS_CACHE_URLS_AS_SAME_PAGE`: Cache URLs with query strings as the same page
     */
    public int $queryStringCaching = self::QUERY_STRINGS_DO_NOT_CACHE_URLS;

    /**
     * The query string parameters to include when determining if and how a page should be cached (regular expressions may be used).
     *
     * [
     *     [
     *         'siteId' => '',
     *         'queryStringParam' => '.*',
     *     ],
     * ]
     *
     * Must accept a string type so the default can be overwritten.
     */
    public array|string $includedQueryStringParams = [
        [
            'siteId' => '',
            'queryStringParam' => '.*',
        ],
    ];

    /**
     * The query string parameters to exclude when determining if and how a page should be cached (regular expressions may be used).
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
     *
     *  Must accept a string type so the default can be overwritten.
     */
    public array|string $excludedQueryStringParams = [
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
     * An API key that can be used via a URL (min. 16 characters).
     */
    public string $apiKey = '';

    /**
     * Whether pages containing query string parameters should be generated.
     */
    public bool $generatePagesWithQueryStringParams = true;

    /**
     * Whether asset images should be purged when changed.
     */
    public bool $purgeAssetImagesWhenChanged = true;

    /**
     * Whether the cache should automatically be refreshed after a global set is updated.
     */
    public bool $refreshCacheAutomaticallyForGlobals = true;

    /**
     * Whether the cache should be refreshed when an element is moved within a structure.
     */
    public bool $refreshCacheWhenElementMovedInStructure = true;

    /**
     * Whether the cache should be refreshed when an element is saved but unchanged.
     */
    public bool $refreshCacheWhenElementSavedUnchanged = false;

    /**
     * Whether the cache should be refreshed when an element is saved but not live.
     */
    public bool $refreshCacheWhenElementSavedNotLive = false;

    /**
     * Whether non-HTML responses should be cached.
     */
    public bool $cacheNonHtmlResponses = false;

    /**
     * Whether elements should be tracked in the database.
     */
    public bool $trackElements = true;

    /**
     * Whether element queries should be tracked in the database.
     */
    public bool $trackElementQueries = true;

    /**
     * Whether elements should be cached in the database.
     *
     * @deprecated in 4.4.0. Use [[trackElements]] instead.
     */
    public bool $cacheElements = true;

    /**
     * Whether element queries should be cached in the database.
     *
     * @deprecated in 4.4.0. Use [[trackElementQueries]] instead.
     */
    public bool $cacheElementQueries = true;

    /**
     * The amount of time after which the cache should expire (if not 0).
     *
     * @see ConfigHelper::durationInSeconds()
     */
    public mixed $cacheDuration = null;

    /**
     * Element types that should not be cached (in addition to the defaults).
     *
     * @var string[]
     */
    public array $nonCacheableElementTypes = [];

    /**
     * Source ID attributes for element types (in addition to the defaults).
     *
     * @var string[]
     */
    public array $sourceIdAttributes = [];

    /**
     * Live statuses for element types (in addition to the defaults).
     *
     * @var string[]
     */
    public array $liveStatuses = [];

    /**
     * The integrations to initialise.
     *
     * @var string[]
     */
    public array $integrations = [
        CommerceIntegration::class,
        FeedMeIntegration::class,
        SeomaticIntegration::class,
    ];

    /**
     * The value to send in the cache control header by default, if not null.
     */
    public ?string $defaultCacheControlHeader = 'no-cache, no-store, must-revalidate';

    /**
     * The value to send in the cache control header.
     *
     * https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Cache-Control
     * https://developers.cloudflare.com/cache/concepts/cache-control/
     */
    public string $cacheControlHeader = 'public, s-maxage=31536000, max-age=0';

    /**
     * The value to send in the cache control header when a page’s cache is expired.
     *
     * https://developers.cloudflare.com/cache/concepts/cache-control/#revalidation
     */
    public string $cacheControlHeaderExpired = 'public, s-maxage=5, max-age=0';

    /**
     * Whether an `X-Powered-By: Blitz` header should be sent.
     */
    public bool $sendPoweredByHeader = true;

    /**
     * Whether the "cached on" and "served by" timestamp comments should be appended to the cached output.
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
     * The priority to give the refresh cache job (the lower the number, the higher the priority).
     */
    public int $refreshCacheJobPriority = 10;

    /**
     * The priority to give driver jobs (the lower the number, the higher the priority).
     */
    public int $driverJobPriority = 100;

    /**
     * The time to reserve for queue jobs in seconds.
     */
    public int $queueJobTtr = 300;

    /**
     * The maximum number of times to attempt retrying a failed queue job.
     */
    public int $maxRetryAttempts = 10;

    /**
     * The maximum length of URIs that may be cached. Increasing this value requires manually updating the limit in the `uri` column of the `blitz_caches` database table. Note that the prefix length limit is 3072 bytes for InnoDB tables that use the DYNAMIC or COMPRESSED row format. Assuming a `utf8mb4` character set and a maximum of 4 bytes for each character, this is 768 characters.
     * https://dev.mysql.com/doc/refman/8.0/en/column-indexes.html#column-indexes-prefix
     *
     * Warning: if using the File Storage driver, this value should not exceed 255 unless using a file system that supports longer filenames.
     * https://en.wikipedia.org/wiki/Comparison_of_file_systems#Limits
     */
    public int $maxUriLength = 255;

    /**
     * The time in seconds to wait for mutex locks to be released.
     */
    public int $mutexTimeout = 1;

    /**
     * The paths to executable shell commands.
     *
     * [
     *     'git' => '/usr/bin/git',
     * ]
     *
     * @var array<string, string>
     */
    public array $commands = [];

    /**
     * The name of the JavaScript event that will trigger a script inject.
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

        return $this->refreshMode === self::REFRESH_MODE_CLEAR
            || $this->refreshMode === self::REFRESH_MODE_CLEAR_AND_GENERATE;
    }

    /**
     * Returns whether the cache should be expired on refresh.
     *
     * @since 4.8.0
     */
    public function expireOnRefresh(bool $forceClear = false): bool
    {
        if ($forceClear) {
            return false;
        }

        if (!$this->cachingEnabled) {
            return false;
        }

        return $this->refreshMode === self::REFRESH_MODE_EXPIRE
            || $this->refreshMode === self::REFRESH_MODE_EXPIRE_AND_GENERATE;
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

        return $this->refreshMode === self::REFRESH_MODE_EXPIRE_AND_GENERATE
            || $this->refreshMode === self::REFRESH_MODE_CLEAR_AND_GENERATE;
    }

    /**
     * Returns whether the cache should be purged after being refreshed.
     *
     * @since 4.8.0
     */
    public function purgeAfterRefresh(bool $forceClear = false): bool
    {
        return $this->expireOnRefresh($forceClear);
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
            [
                ['refreshMode'], 'in', 'range' => [
                self::REFRESH_MODE_EXPIRE,
                self::REFRESH_MODE_CLEAR,
                self::REFRESH_MODE_EXPIRE_AND_GENERATE,
                self::REFRESH_MODE_CLEAR_AND_GENERATE,
            ],
            ],
            [
                ['queryStringCaching'], 'in', 'range' => [
                self::QUERY_STRINGS_DO_NOT_CACHE_URLS,
                self::QUERY_STRINGS_CACHE_URLS_AS_UNIQUE_PAGES,
                self::QUERY_STRINGS_CACHE_URLS_AS_SAME_PAGE,
            ],
            ],
            [['apiKey'], 'string', 'length' => [16]],
            [['cachingEnabled', 'cacheElements', 'cacheElementQueries'], 'boolean'],
        ];
    }
}
