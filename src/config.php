<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

/**
 * Blitz config.php
 *
 * This file exists only as a template for the Blitz settings.
 * It does nothing on its own.
 *
 * Don't edit this file, instead copy it to 'craft/config' as 'blitz.php'
 * and make your changes there to override default settings.
 *
 * Once copied to 'craft/config', this file will be multi-environment aware as
 * well, so you can have different settings groups for each environment, just as
 * you do for 'general.php'
 */

return [
    '*' => [
        // With this setting enabled, Blitz will log detailed messages to `storage/logs/blitz.log`.
        //'debug' => false,

        // With this setting enabled, Blitz will provide template performance hints in a utility.
        //'hintsEnabled' => true,

        // With this setting enabled, Blitz will begin caching pages according to the included/excluded URI patterns. Disable this setting to prevent Blitz from caching any new pages.
        //'cachingEnabled' => false,

        //With this setting enabled, Blitz will refresh cached pages whenever content changes or an integration triggers it. Disable this setting to prevent Blitz from refreshing cached pages.
        //'refreshCacheEnabled' => true,

        // Determines when and how the cache should be refreshed.
        // `\putyourlightson\blitz\models\SettingsModel::REFRESH_MODE_CLEAR_AND_GENERATE`: Clear the cache and regenerate in a queue job
        // `\putyourlightson\blitz\models\SettingsModel::REFRESH_MODE_EXPIRE_AND_GENERATE`: Expire the cache and regenerate in a queue job
        // `\putyourlightson\blitz\models\SettingsModel::REFRESH_MODE_CLEAR`: Clear the cache, regenerate manually or organically
        // `\putyourlightson\blitz\models\SettingsModel::REFRESH_MODE_EXPIRE`: Expire the cache, regenerate manually or organically*
        //'refreshMode' => \putyourlightson\blitz\models\SettingsModel::REFRESH_MODE_CLEAR_AND_GENERATE,

        // The URI patterns to include in caching. Set `siteId` to a blank string to indicate all sites.
        //'includedUriPatterns' => [
        //    [
        //        'siteId' => 1,
        //        'uriPattern' => 'pages/.*',
        //    ],
        //    [
        //        'siteId' => 2,
        //        'uriPattern' => 'articles/.*',
        //    ],
        //],

        // The URI patterns to exclude from caching (overrides any matching patterns to include). Set `siteId` to a blank string to indicate all sites.
        //'excludedUriPatterns' => [
        //    [
        //        'siteId' => 1,
        //        'uriPattern' => 'pages/contact',
        //    ],
        //],

        // The storage type to use.
        //'cacheStorageType' => 'putyourlightson\blitz\drivers\storage\FileStorage',

        // The storage settings.
        //'cacheStorageSettings' => [
        //    'folderPath' => '@webroot/cache/blitz',
        //    'compressCachedValues' => false,
        //    'countCachedFiles' => true,
        //],

        // The storage type classes to add to the plugin’s default storage types.
        //'cacheStorageTypes' => [],

        // The generator type to use.
        //'cacheGeneratorType' => 'putyourlightson\blitz\drivers\generators\HttpGenerator',

        // The generator settings.
        //'cacheGeneratorSettings' => ['concurrency' => 3],

        // The generator type classes to add to the plugin’s default generator types.
        //'cacheGeneratorTypes' => [],

        // Custom site URIs to generate when either a site or the entire cache is generated.
        //'customSiteUris' => [
        //    [
        //        'siteId' => 1,
        //        'uri' => 'pages/custom',
        //    ],
        //],

        // The purger type to use.
        //'cachePurgerType' => 'putyourlightson\blitz\drivers\purgers\CloudflarePurger',

        // The purger settings (zone ID keys are site UIDs).
        //'cachePurgerSettings' => [
        //    'zoneIds' => [
        //        'f64d4923-f64d4923-f64d4923' => [
        //            'zoneId' => '',
        //        ],
        //    ],
        //    'authenticationMethod' => 'apiKey',
        //    'apiKey' => '',
        //    'email' => '',
        //],

        // The purger type classes to add to the plugin’s default purger types.
        //'cachePurgerTypes' => [
        //    'putyourlightson\blitzcloudfront\CloudFrontPurger',
        //],

        // The deployer type to use.
        //'deployerType' => 'putyourlightson\blitz\drivers\deployers\GitDeployer',

        // The deployer settings (zone ID keys are site UIDs).
        //'deployerSettings' => [
        //    'gitRepositories' => [
        //        'f64d4923-f64d4923-f64d4923' => [
        //            'repositoryPath' => '@root/path/to/repo',
        //            'branch' => 'main',
        //            'remote' => 'origin',
        //        ],
        //    ],
        //    'commitMessage' => 'Blitz auto commit',
        //    'username' => '',
        //    'personalAccessToken' => '',
        //    'name' => '',
        //    'email' => '',
        //    'commandsBefore' => '',
        //    'commandsAfter' => '',
        //],

        // The deployer type classes to add to the plugin’s default deployer types.
        //'deployerTypes' => [
        //    'putyourlightson\blitzshell\ShellDeployer',
        //],

        // With this setting enabled, Blitz will statically include templates using Server-Side Includes (SSI), which must be enabled on the web server.
        //'ssiEnabled' => false,

        // The format to use for SSI tags, in which `{uri}` will be replaced. You can change this when using Caddy’s `httpInclude` template function, for example.
        // https://caddyserver.com/docs/modules/http.handlers.templates#httpinclude
        //'ssiTagFormat' => '<!--#include virtual="{uri}" -->',

        // Whether SSI detection via the control panel should be enabled.
        //'detectSsiEnabled' => true,

        // With this setting enabled, Blitz will statically include templates using Edge-Side Includes (ESI), which must be enabled on the web server or CDN.
        //'esiEnabled' => false,

        // Whether URLs with query strings should be cached and how.
        // - `0`: Do not cache URLs with query strings
        // - `1`: Cache URLs with query strings as unique pages
        // - `2`: Cache URLs with query strings as the same page
        //'queryStringCaching' => 0,

        // The query string parameters to include when determining if and how a page should be cached (regular expressions may be used).
        //'includedQueryStringParams' => [
        //    [
        //        'siteId' => '',
        //        'queryStringParam' => '.*',
        //    ],
        //],

        // The query string parameters to exclude when determining if and how a page should be cached (regular expressions may be used).
        //'excludedQueryStringParams' => [
        //    [
        //        'siteId' => '',
        //        'queryStringParam' => 'gclid',
        //    ],
        //    [
        //        'siteId' => '',
        //        'queryStringParam' => 'fbclid',
        //    ],
        //],

        // An API key that can be used via a URL (min. 16 characters).
        //'apiKey' => '',

        // Whether pages containing query string parameters should be generated.
        //'generatePagesWithQueryStringParams' => true,

        // Whether asset images should be purged when changed.
        //'purgeAssetImagesWhenChanged' => true,

        // Whether the cache should automatically be refreshed after a global set is updated.
        //'refreshCacheAutomaticallyForGlobals' => true,

        // Whether the cache should be refreshed when an element is moved within a structure.
        //'refreshCacheWhenElementMovedInStructure' => true,

        // Whether the cache should be refreshed when an element is saved but unchanged.
        //'refreshCacheWhenElementSavedUnchanged' => false,

        // Whether the cache should be refreshed when an element is saved but not live.
        //'refreshCacheWhenElementSavedNotLive' => false,

        // Whether non-HTML responses should be cached. With this setting enabled, Blitz will also cache pages that return non-HTML responses. If enabled, you should ensure that URIs that should not be caches, such as API endpoints, XML sitemaps, etc. are added as excluded URI patterns.
        //'cacheNonHtmlResponses' => false,

        // Whether elements should be tracked in the database.
        //'trackElements' => true,

        // Whether element queries should be tracked in the database.
        //'trackElementQueries' => true,

        // The element query params to exclude when storing tracked element queries.
        //'excludedTrackedElementQueryParams' => [
        //    'limit',
        //    'offset',
        //],

        // The amount of time after which the cache should expire (if not 0). See [[ConfigHelper::durationInSeconds()]] for a list of supported value types.
        //'cacheDuration' => 0,

        // Element types that should not be cached (in addition to the defaults).
        //'nonCacheableElementTypes' => [
        //    'putyourlightson\campaign\elements\ContactElement',
        //],

        // Source ID attributes for element types (in addition to the defaults).
        //'sourceIdAttributes' => [
        //    'putyourlightson\campaign\elements\CampaignElement' => 'campaignTypeId',
        //],

        // The integrations to initialise.
        //'integrations' => [
        //    'putyourlightson\blitz\drivers\integrations\CommerceIntegration',
        //    'putyourlightson\blitz\drivers\integrations\FeedMeIntegration',
        //    'putyourlightson\blitz\drivers\integrations\SeomaticIntegration',
        //],

        // The value to send in the cache control header by default, if not null.
        //'defaultCacheControlHeader' => 'no-cache, no-store, must-revalidate',

        // The value to send in the cache control header.
        //'cacheControlHeader' => 'public, s-maxage=31536000, max-age=0',

        // The value to send in the cache control header when a page is expired.
        //'cacheControlHeaderExpired' => 'public, s-maxage=5, max-age=0',

        // Whether an `X-Powered-By: Blitz` header should be sent.
        //'sendPoweredByHeader' => true,

        // Whether the "cached on" and "served by" timestamp comments should be appended to the cached output.
        // - `false`: Do not append any comments
        // - `true`: Append all comments
        // - `2`: Append "cached on" comment only
        // - `3`: Append "served by" comment only
        //'outputComments' => true,

        // The priority to give the refresh cache job (the lower the number, the higher the priority). Set to `null` to inherit the default priority.
        //'refreshCacheJobPriority' => 10,

        // The priority to give driver jobs (the lower the number, the higher the priority). Set to `null` to inherit the default priority.
        //'driverJobPriority' => 100,

        // The time to reserve for queue jobs in seconds.
        //'queueJobTtr' => 300,

        // The maximum number of times to attempt retrying a failed queue job.
        //'maxRetryAttempts' => 10,

        // The maximum length of URIs that may be cached. Increasing this value requires manually updating the limit in the `uri` column of the `blitz_caches` database table. Note that the prefix length limit is 3072 bytes for InnoDB tables that use the DYNAMIC or COMPRESSED row format. Assuming a `utf8mb4` character set and a maximum of 4 bytes for each character, this is 768 characters.
        // https://dev.mysql.com/doc/refman/8.0/en/column-indexes.html#column-indexes-prefix
        // Warning: if using the File Storage driver, this value should not exceed 255 unless using a file system that supports longer file names.
        // https://en.wikipedia.org/wiki/Comparison_of_file_systems#Limits
        //'maxUriLength' => 255,

        // The time in seconds to wait for mutex locks to be released.
        //'mutexTimeout' => 1,

        // The paths to executable shell commands.
        //'commands' => [
        //    'git' => '/usr/bin/git',
        //],

        // The name of the JavaScript event that will trigger a script inject.
        //'injectScriptEvent' => 'DOMContentLoaded',

        // The position in the HTML of the injected script.
        //'injectScriptPosition' => yii\web\View::POS_END,
    ],
];
