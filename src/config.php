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
    // Whether caching should be enabled. With this setting enabled, Blitz will begin caching pages according to the included/excluded URI patterns. Disable this setting to prevent Blitz from caching any new pages.
    //'cachingEnabled' => false,

    // The URI patterns to include in caching. The second variable represents a site ID, or a blank string for all sites.
    //'includedUriPatterns' => [['pages/.*', '1'], ['articles/.*', '2']],

    // The URI patterns to exclude from caching (overrides any matching patterns to include). The second variable represents a site ID, or a blank string for all sites.
    //'excludedUriPatterns' => [['contact', '']],

    // The storage type to use.
    //'cacheStorageType' => 'putyourlightson\blitz\drivers\storage\FileStorage',

    // The storage settings.
    //'cacheStorageSettings' => ['folderPath' => 'cache/blitz'],

    // The storage type classes to add to the plugin’s default storage types.
    //'cacheStorageTypes' => [],

    // The purger type to use.
    //'cachePurgerType' => 'putyourlightson\blitz\drivers\purgers\CloudflarePurger',

    // The purger settings (each purger can have its own unique settings).
    //'cachePurgerSettings' => [
    //    'email' => '',
    //    'apiKey' => '',
    //    'zoneId' => '',
    //],

    // The purger type classes to add to the plugin’s default purger types.
    //'cachePurgerTypes' => [
    //    'putyourlightson\blitz\drivers\purgers\CloudflarePurger',
    //],

    // Whether the cache should automatically be cleared when elements are updated.
    //'clearCacheAutomatically' => true,

    // Whether the cache should automatically be cleared after clearing globals.
    //'clearCacheAutomaticallyForGlobals' => true,

    // Whether the cache should automatically be warmed after clearing.
    //'warmCacheAutomatically' => true,

    // Whether the cache should automatically be warmed after clearing globals.
    //'warmCacheAutomaticallyForGlobals' => true,

    // The max number of multiple concurrent requests to use when warming the cache. The higher the number, the faster the cache will be warmed and the more server processing will be required. A number between 1 and 5 is recommended.
    //'concurrency' => 3,

    // Whether URLs with query strings should cached and how.
    // 0: Do not cache URLs with query strings
    // 1: Cache URLs with query strings as unique pages
    // 2: Cache URLs with query strings as the same page
    //'queryStringCaching' => 0,

    // An API key that can be used to clear, flush, warm, or refresh expired cache through a URL (min. 16 characters).
    //'apiKey' => '',

    // Whether elements should be cached in the database.
    //'cacheElements' => true,

    // Whether element queries should be cached in the database.
    //'cacheElementQueries' => true,

    // The amount of time after which the cache should expire (if not 0). See [[ConfigHelper::durationInSeconds()]] for a list of supported value types.
    //'cacheDuration' => 0,

    // Element types that should not be cached.
    //'nonCacheableElementTypes' => [
    //    'craft\elements\GlobalSet',
    //    'craft\elements\MatrixBlock',
    //    'benf\neo\elements\Block',
    //    'putyourlightson\campaign\elements\ContactElement',
    //],

    // The integrations to initialise.
    //'integrations' => [
    //    'putyourlightson\blitz\drivers\integrations\SeomaticIntegration',
    //],

    // The value to send in the cache control header.
    //'cacheControlHeader' => 'public, s-maxage=0',

    // Whether an `X-Powered-By: Craft CMS, Blitz` header should be sent.
    //'sendPoweredByHeader' => true,

    // Whether the timestamp and served by comments should be appended to the cached output.
    //'outputComments' => true,
];
