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

        // With this setting enabled, Blitz will begin caching pages according to the included/excluded URI patterns. Disable this setting to prevent Blitz from caching any new pages.
        //'cachingEnabled' => false,

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
        //    'createGzipFiles' => false,
        //    'countCachedFiles' => true,
        //],

        // The storage type classes to add to the plugin’s default storage types.
        //'cacheStorageTypes' => [],

        // The warmer type to use.
        //'cacheWarmerType' => 'putyourlightson\blitz\drivers\warmers\GuzzleWarmer',

        // The warmer settings.
        //'cacheWarmerSettings' => ['concurrency' => 3],

        // The warmer type classes to add to the plugin’s default warmer types.
        //'cacheWarmerTypes' => [],

        // Custom site URIs to warm when either a site or the entire cache is warmed.
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
        //    'email' => '',
        //    'apiKey' => '',
        //    'warmCacheDelay' => '5',
        //],

        // The purger type classes to add to the plugin’s default purger types.
        //'cachePurgerTypes' => [
        //    'putyourlightson\blitzshell\ShellDeployer',
        //],

        // The deployer type to use.
        //'deployerType' => 'putyourlightson\blitz\drivers\deployers\GitDeployer',

        // The deployer settings (zone ID keys are site UIDs).
        //'deployerSettings' => [
        //    'gitRepositories' => [
        //        'f64d4923-f64d4923-f64d4923' => [
        //            'repositoryPath' => '@root/path/to/repo',
        //            'branch' => 'master',
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

        // Whether the cache should automatically be cleared when elements are updated.
        //'clearCacheAutomatically' => true,

        // Whether the cache should automatically be warmed (and deployed) after clearing.
        //'warmCacheAutomatically' => true,

        // Whether pages containing query string parameters should be warmed.
        //'warmPagesWithQueryStringParams' => true,

        // Whether the cache should automatically be refreshed after a global set is updated.
        //'refreshCacheAutomaticallyForGlobals' => true,

        // Whether the cache should be refreshed when an element is saved but unchanged.
        //'refreshCacheWhenElementSavedUnchanged' => false,

        // Whether the cache should be refreshed when an element is saved but not live.
        //'refreshCacheWhenElementSavedNotLive' => false,

        // Whether URLs with query strings should be cached and how.
        // - `0`: Do not cache URLs with query strings
        // - `1`: Cache URLs with query strings as unique pages
        // - `2`: Cache URLs with query strings as the same page
        //'queryStringCaching' => 0,

        // The query string parameters to include when determining if and how a page should be cached (regular expressions may be used).
        //'includedQueryStringParams' => ['.*'],

        // The query string parameters to exclude when determining if and how a page should be cached (regular expressions may be used).
        //'excludedQueryStringParams' => ['gclid', 'fbclid'],

        // An API key that can be used to clear, flush, warm, or refresh expired cache through a URL (min. 16 characters).
        //'apiKey' => '',

        // A path to the `bin` folder that should be forced.
        //'binPath' => '',

        // Whether elements should be cached in the database.
        //'cacheElements' => true,

        // Whether element queries should be cached in the database.
        //'cacheElementQueries' => true,

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
        //    'putyourlightson\blitz\drivers\integrations\FeedMeIntegration',
        //    'putyourlightson\blitz\drivers\integrations\SeomaticIntegration',
        //],

        // The value to send in the cache control header.
        //'cacheControlHeader' => 'public, s-maxage=31536000, max-age=0',

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

        // The time in seconds to wait for mutex locks to be released.
        //'mutexTimeout' => 1,

        // The paths to executable shell commands.
        //'commands' => [
        //    'git' => '/usr/bin/git',
        //],

        // The name of the JavaScript event that will trigger a script inject.
        //'injectScriptEvent' => 'DOMContentLoaded',
    ],
];
