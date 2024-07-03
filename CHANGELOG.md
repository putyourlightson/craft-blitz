# Release Notes for Blitz

## 4.18.4 - 2024-07-03

### Fixed

- Fixed a bug in which the cached page count of sites that contained the paths of other sites could be inaccurately displayed in the Blitz Cache utility.

## 4.18.3 - 2024-06-27

### Fixed

- Fixed a bug in which the priority of refresh cache and driver jobs was interpreted as `0` when set to `null` ([#655](https://github.com/putyourlightson/craft-blitz/issues/655)).
- Fixed an issue in which the priority of batch jobs could be a negative number and therefore jobs would never complete.

## 4.18.2 - 2024-06-18

### Fixed

- Fixed a bug in which modules that were not bootstrapped were throwing an error when generating a report in the Blitz Diagnostics utility ([#668](https://github.com/putyourlightson/craft-blitz/issues/668)). 
- Fixed a bug in which incorrect purge requests were being sent to CloudFront for the homepage ([#673](https://github.com/putyourlightson/craft-blitz/issues/673)).

## 4.18.1 - 2024-05-16

### Added

- Added a tracked fields column to the tracked elements page in the Blitz Diagnostics utility.

### Changed

- Sites in the Blitz diagnostics report are now sorted by ID in ascending order.

### Fixed

- Fixed a bug in the Git Deployer that was throwing an error when a cached page no longer existed ([#664](https://github.com/putyourlightson/craft-blitz/issues/664)). 

## 4.18.0 - 2024-05-07

### Added

- Added anonymised site names to the Blitz diagnostics report.
- Added a detailed breakdown of element types to the Blitz diagnostics report.
- Added the ability to download the Blitz diagnostics report as a markdown file.

### Changed

- Optimised the refresh cache process by excluding redundantly tracked element queries based on their limit and offset values.

### Fixed

- Fixed the check for whether the `blitz/cache/refresh-expired` console command was executed within the past 24 hours.
- Fixed diagnostics notifications in the control panel.

## 4.17.0 - 2024-04-27

### Added

- Added the ability to generate a diagnostics report in the Blitz Diagnostics utility, that can be shared when requesting support. 

## 4.16.5 - 2024-04-26

### Fixed

- Fixed a bug in the SQL statement introduced in 4.16.4 when using a Postgres database. 

## 4.16.4 - 2024-04-26

### Changed

- Improved the deletion of cache records during the refresh cache process to help avoid database memory issues.

## 4.16.3 - 2024-04-22

### Changed

- The `blitz/cache/refresh-cache-tags` and `blitz/cache/refresh-expired-elements` no longer forcibly generate the cache.

## 4.16.2 - 2024-04-12

### Changed

- Dynamic includes in preview requests are now also sent via AJAX, passing through the token param ([#653](https://github.com/putyourlightson/craft-blitz/issues/653)).

## 4.16.1 - 2024-04-12

### Fixed

- Fixed a bug in which the Blitz Diagnostic utility could throw an error when viewing tracked includes and when using Postgres ([#653](https://github.com/putyourlightson/craft-blitz/issues/653)).
- Fixed an edge-case bug in which cached includes were not being refreshed when expired in a multi-site setup using subfolders.

## 4.16.0 - 2024-04-10

### Added

- Added tracked includes to the Blitz Diagnostics utility.

### Fixed

- Fixed a bug in which cached includes were not being refreshed when a URL was provided.
- Fixed an edge-case bug in which cached includes were not being refreshed when expired.

## 4.15.0 - 2024-04-08

### Added

- Added a new `blitz/cache/refresh-expired-elements` console command for refreshing cached pages that track expired elements.
- Added the `BaseCachePurger::shouldPurgeAfterRefresh()` method.

### Changed

- Drivers are now listed in alphabetical order, with “None” always listed first.
- Improved the progress indicator during the generate cache process.
- Simplified the default cache control header settings.
- Pages that return empty content are no longer ignored from being cached. 
- The `Content-Encoding` header is no longer removed from responses.

### Fixed

- Fixed a bug in which expiry dates were always being output using the current timestamp in the Blitz Diagnostics utility.
- Fixed an edge-case bug in which cached includes were not being refreshed when expired.

## 4.14.1 - 2024-03-25

### Changed

- Cached and dynamic includes are now resolved as regular includes when previewing an element ([#646](https://github.com/putyourlightson/craft-blitz/issues/646)).

### Fixed

- Fixed an error that could be thrown when returning to a page via the breadcrumbs in the Blitz Diagnostics utility ([#645](https://github.com/putyourlightson/craft-blitz/issues/645)).

## 4.14.0 - 2024-03-20

### Added

- Added batching to generate cache jobs ([#537](https://github.com/putyourlightson/craft-blitz/issues/537)).
- Added a new `driverJobBatchSize` config setting that sets the batch size to use for driver jobs that support batching.
- Added a new `refreshCacheEnabled` config setting that determines whether cached pages are refreshed whenever content changes or an integration triggers it.
- Added a new `injectScriptPosition` config setting that determines the position in the HTML in which to output the injected script ([#636](https://github.com/putyourlightson/craft-blitz/issues/636)).
- Added a verbose output mode to `blitz/cache` console commands that can be activated by adding a `--verbose` flag ([#642](https://github.com/putyourlightson/craft-blitz/issues/642)).
- Added a default timeout of 60 seconds to the Local Generator.

### Changed

- Campaign now requires Craft CMS 4.4.0 or later.
- The Local Generator now uses the `bootstrap.php` file in the project root, if it exists.
- The Local Generator now sets the server port according to the HTTP protocol.
- Changed the default timeout of the HTTP Generator to 60 seconds.

### Fixed

- Fixed an SQL error that could occur when too many site URIs were being expired at once during the refresh cache process ([#639](https://github.com/putyourlightson/craft-blitz/issues/639)).
- Fixed minor bugs and typos in the recommendations provided in the Blitz Diagnostics utility ([#641](https://github.com/putyourlightson/craft-blitz/issues/641)).

### Deprecated

- Deprecated the `SettingsModel::clearOnRefresh` method. Use `SettingsModel::shouldClearOnRefresh` instead.
- Deprecated the `SettingsModel::expireOnRefresh` method. Use `SettingsModel::shouldExpireOnRefresh` instead.
- Deprecated the `SettingsModel::generateOnRefresh` method. Use `SettingsModel::shouldGenerateOnRefresh` instead.
- Deprecated the `SettingsModel::purgeAfterRefresh` method. Use `SettingsModel::shouldPurgeAfterRefresh` instead.
- Deprecated the `SettingsModel::generatePageBasedOnQueryString` method. Use `SettingsModel::shouldGeneratePageBasedOnQueryString` instead.
- Deprecated the `SettingsModel::purgeAssetImages` method. Use `SettingsModel::shouldPurgeAssetImages` instead.

## 4.13.1 - 2024-03-12

### Changed

- The template stack trace in the Blitz Hints utility is now updated if different for the same field-template combination.

### Fixed

- Fixed a bug in the Blitz Hints utility that could throw an error when conditional variables were used in an `extends` tag ([#632](https://github.com/putyourlightson/craft-blitz/issues/632)).

## 4.13.0 - 2024-03-11

### Added

- Added a template stack trace to the Blitz Hints utility.

### Changed

- Elements that are propagating are no longer ignored from the cache refresh process ([#631](https://github.com/putyourlightson/craft-blitz/issues/631)).
- Changed the default branch in the Git Deployer to `main`.

## 4.12.3 - 2024-03-08

### Fixed

- Fixed a bug in which blank rows were appearing in the Blitz Diagnostics utility.

## 4.12.2 - 2024-03-05

### Added

- Added tracked tags to the Blitz Diagnostics utility.

### Changed

- Diagnostics warnings are now listed before recommendations that pass.
- A custom log target is now only registered if a dispatcher exists.

## 4.12.1 - 2024-02-27

### Fixed

- Fixed a bug introduced in version 4.12.0 in which the Blitz inject script was not being registered.

## 4.12.0 - 2024-02-27 [CRITICAL]

> {warning} Polyfills for IE have been removed from the Blitz inject script due to `polyfill.io` having been compromised.

### Added

- Added a new `excludedTrackedElementQueryParams` config setting that can be used to exclude params when storing tracked element queries.

### Changed

- Reverted deleting expired URIs from the database immediately when refreshing expired cache, as it could cause cached pages not to be refreshed ([#624](https://github.com/putyourlightson/craft-blitz/issues/624)).
- Tracked element queries that have invalid params or that cannot be executed are now deleted when refreshing the cache.
- Tracked entry queries can now determine source IDs from a structure ID.
- Optimised the params that are stored on tracked element queries. 
- Made the ordering of items displayed in the Blitz Diagnostics utility deterministic.

### Removed

- Removed the `fetch`, `Promise` and `CustomEvent` polyfills for IE from the Blitz inject script – if you need to support IE, you will need to add the polyfills yourself.

## 4.11.2 - 2024-02-19

### Changed

- Asset queries containing filenames are no longer tracked.

### Fixed

- Fixed a bug in the diagnostics utility that only affected Postgres ([#621](https://github.com/putyourlightson/craft-blitz/issues/621)).

## 4.11.1 - 2024-02-12

### Fixed

- Fixed the tracking of non-cacheable block elements when eager-loaded.

## 4.11.0 - 2024-02-12

### Added

- Added recommendations to the Blitz Diagnostics utility.
- Added the user that runs controller actions to log messages.
- Added an expiry date column to pages and elements in the Blitz Diagnostics utility ([#615](https://github.com/putyourlightson/craft-blitz/issues/615)).
- Added a `wrapperClass` property to the `VariableConfigModel` class ([#620](https://github.com/putyourlightson/craft-blitz/issues/620)).
- Added new console commands for clearing, generating and purging the cache: `blitz/cache/clear-site`, `blitz/cache/clear-tagged`, `blitz/cache/clear-urls`, `blitz/cache/generate-site`, `blitz/cache/generate-tagged`, `blitz/cache/generate-urls`, `blitz/cache/purge-site`, `blitz/cache/purge-tagged`, `blitz/cache/purge-urls`.

### Changed

- The Cache Folder Path column now displays aliases in the cached file table in the File Storage utility.
- Entry queries for “single” sections are no longer tracked.
- Improved error handling in a previous migration.
- Made minor optimisations to the file storage driver.
- Expired URIs are now deleted from the database immediately when refreshing expired cache.
- Invalid element query params no longer throw an error when applied ([#579](https://github.com/putyourlightson/craft-blitz/issues/579)).

### Fixed

- Fixed a bug in which line breaks were not being replaced with spaces in raw SQL query output in the Blitz Diagnostics utility ([#611](https://github.com/putyourlightson/craft-blitz/issues/611)).

## 4.10.3 - 2024-01-13

### Fixed

- Fixed more Postgres errors.

## 4.10.2 - 2024-01-12

### Fixed

- Fixed Postgres errors introduced in 4.10.0 ([#605](https://github.com/putyourlightson/craft-blitz/issues/605)).

## 4.10.1 - 2024-01-10

### Fixed

- Fixed a bug in which SQL queries were executed in the Blitz Diagnostics utility that required minimum database versions above those required by Craft 4 ([#602](https://github.com/putyourlightson/craft-blitz/issues/602)).
- Fixed a bug in which paginated pages could go beyond their maximum in the Blitz Diagnostics utility ([#603](https://github.com/putyourlightson/craft-blitz/issues/603)).
- Fixed a bug in which Sprig Core was not being bootstrapped for all CP requests.
- Fixed a bug in which legacy non-cacheable element types were causing errors in the Blitz Diagnostics utility.

## 4.10.0 - 2024-01-09

### Added

- Added the Blitz Diagnostics utility ([read the announcement](https://putyourlightson.com/articles/introducing-blitz-diagnostics)).

### Fixed

- Fixed a bug in which all cached pages were being marked as expired when no cacheable site URIs were refreshed ([#598](https://github.com/putyourlightson/craft-blitz/issues/598)).

## 4.9.4 - 2023-12-19

### Changed

- The path param is now removed from query strings before being cached.
- Slashes in cached include URLs are no longer decoded ([#595](https://github.com/putyourlightson/craft-blitz/issues/595)).

### Fixed

- Fixed a bug that could cause an error when an element’s ID is unset ([#594](https://github.com/putyourlightson/craft-blitz/issues/594)).

## 4.9.3 - 2023-11-28

### Changed

- Send site URIs to the `EVENT_AFTER_REFRESH_ALL_CACHE` event if cache generation is enabled.

## 4.9.2 - 2023-11-28

### Changed

- Integrity constraint violation exceptions are now caught when batch inserting rows into the database ([#588](https://github.com/putyourlightson/craft-blitz/issues/588)).
- Reverted sending site URIs to the refresh cache event ([#590](https://github.com/putyourlightson/craft-blitz/issues/590)).

### Fixed

- Fixed a bug in which eager-loading custom fields of preloaded singles was not being tracked on cached pages ([#585](https://github.com/putyourlightson/craft-blitz/issues/585)).

## 4.9.1 - 2023-11-20

### Added

- Added a `ssiTagFormat` config setting that allows defining the format of SSI tags ([#558](https://github.com/putyourlightson/craft-blitz/issues/558)).
- Added a `detectSsiEnabled` config setting that determines whether Blitz should detect whether SSI is enabled on the web server ([#575](https://github.com/putyourlightson/craft-blitz/issues/575)).

## 4.9.0 - 2023-11-17

### Added

- Added the ability to configure a custom queue component via `config/app.php` to use when running queue jobs ([#577](https://github.com/putyourlightson/craft-blitz/issues/577)).

### Fixed

- Fixed a bug in which previewing a disabled site could throw an exception with the File Cache Storage selected ([#581](https://github.com/putyourlightson/craft-blitz/issues/581)).

## 4.8.0 - 2023-11-14

### Added

- Added a new `ExpireCacheService` class that handles marking cache as expired when the refresh mode is set to expire the cache.
- Added a new `cacheControlHeaderExpired` config setting that reduces the max cache age in public reverse proxies to 5 seconds by default for expired pages.
- Added a new `defaultCacheControlHeader` config setting that is sent by default if no other cache headers are sent ([#580](https://github.com/putyourlightson/craft-blitz/issues/580)).

### Changed

- Cache control headers are now set to the new `cacheControlHeaderExpired` config setting when a cached response is sent for an expired page, meaning that expired cache can now be organically regenerated.
- Cached pages are now expired when refreshed via the utility or console commands and when the refresh mode is set to expire the cache.

## 4.7.1 - 2023-11-03

### Changed

- Bumped the required version of the Blitz Hints package.

### Fixed

- Fixed a potential issue with detecting whether SSI is enabled on the web server from the control panel.
- Fixed a bug in which generating the cache could throw an exception if no custom site URLs were added in the settings ([#578](https://github.com/putyourlightson/craft-blitz/issues/578)).

## 4.7.0 - 2023-10-26

### Added

- Added a `DummyStorage` class that allows the cache storage driver to be set to `None`, useful if pages should be cached on a reverse proxy only ([#502](https://github.com/putyourlightson/craft-blitz/issues/502)).

### Changed

- Refreshing expired cached now forcibly generates new cached pages if they are not cleared ([#571](https://github.com/putyourlightson/craft-blitz/issues/571)).
- Changed the refreshable status check to always consider elements with `live` and `active` statuses as refreshable ([#572](https://github.com/putyourlightson/craft-blitz/issues/572)).

### Fixed

- Fixed a bug in which the cached include path could be incorrectly set if specific included query string parameters were selected ([#573](https://github.com/putyourlightson/craft-blitz/issues/573)).
- Fixed a bug in which saving included and excluded query string parameters was not possible were no values were specified.

## 4.6.0 - 2023-10-17

### Added

- Added the ability for Blitz to track disabled elements in relation field queries so that cached pages are refreshed when their status is set to enabled ([#555](https://github.com/putyourlightson/craft-blitz/issues/555)).

### Changed

- Changed the dynamic include path to account for sites that live within a subfolder ([#562](https://github.com/putyourlightson/craft-blitz/issues/562)).
- Include action tags now ensure that slashes are not encoded to account for URL encoding issues ([#564](https://github.com/putyourlightson/craft-blitz/issues/564)).

## 4.5.5 - 2023-09-20

### Fixed

- Fixed a potential security issue.

## 4.5.4 - 2023-09-15

### Fixed

- Fixed a bug in which element query params containing multi and single option data were not being converted to values.
- Fixed a bug in which error exceptions were not being caught when produced by cached element queries during the refresh cache process.

## 4.5.3 - 2023-09-12

### Fixed

- Fixed a bug in which cached pages were not being cleared when using the Yii Cache Storage driver with gzip compression enabled.

## 4.5.2 - 2023-08-14

### Fixed

- Fixed a bug in which tracked element queries were ignoring disabled elements when determining which cached pages to refresh ([#527](https://github.com/putyourlightson/craft-blitz/issues/527)).

## 4.5.1 - 2023-08-09

### Fixed

- Fixed a bug in which using dynamic includes with nginx server rewrites set to cache pages with query strings as the same page could incorrectly include the home page.

## 4.5.0 - 2023-07-19

> {warning} The cache must be cleared or refreshed after this update completes.

### Added

- Added the ability to send compressed responses to browsers that accept supported encodings.
- Added the ability to save compressed cached values in the Yii Cache Storage to help reduce the memory required.
- Added tips that display whether `gzip` is enabled on the web server in the Cache Storage settings.
- Added the `maxUriLength` config setting ([#539](https://github.com/putyourlightson/craft-blitz/issues/539)).

### Changed

- Cached includes and pages that contain SSI or ESI includes are now never compressed.
- Renamed the `createGzipFiles` setting to `compressCachedValues`.
- Improved the performance of cache refresh jobs by optimising database queries ([#496](https://github.com/putyourlightson/craft-blitz/issues/496)).
- The cache refresh process is now triggered when an asset’s file is replaced or its filename is changed ([#514](https://github.com/putyourlightson/craft-blitz/issues/514)).
- Changed the URL that checks whether SSI is enabled on the web server to a relative URL.

### Removed

- Removed the ability to create Brotli files and removed the setting (use gzip instead).

### Fixed

- Fixed a bug in which the `cacheDuration` config setting was not being applied when the value was not an integer ([#536](https://github.com/putyourlightson/craft-blitz/issues/536)).
- Fixed a bug in which the `__home__` URI was not responding with a 404 error when it should have ([#538](https://github.com/putyourlightson/craft-blitz/issues/538)).
- Fixed a bug in which eager-loading of auto-injected elements was not being tracked on cached pages.

### Deprecated

- Deprecated the `createGzipFiles` setting.
- Deprecated the `createBrotliFiles` setting.

## 4.4.7 - 2023-07-17

### Changed

- Hardened checks against null responses to help avoid errors ([#519](https://github.com/putyourlightson/craft-blitz/issues/519)).

### Fixed

- Fixed a bug in which refreshing the cache could fail when using the Redis queue driver ([#522](https://github.com/putyourlightson/craft-blitz/issues/522)).
- Fixed a bug in which URLs containing the control panel trigger were not being cached ([#532](https://github.com/putyourlightson/craft-blitz/issues/532)).
- Fixed a bug in which a validation error could occur when an invalid email address was entered in the Cloudflare API Key Email field even when the authentication method was set to API Token.
- Fixed a race condition that could result in an SQL error if the database used read/write splitting ([#531](https://github.com/putyourlightson/craft-blitz/issues/531)).
- Fixed the `getUri` deprecation notice to suggest `fetchUri` instead of `fetch` ([#508](https://github.com/putyourlightson/craft-blitz/issues/508), [#524](https://github.com/putyourlightson/craft-blitz/issues/524)).

## 4.4.6 - 2023-06-28

> {warning} To ensure the fix is applied, the cache should be cleared or refreshed after this update completes.

### Fixed

- Fixed a bug introduced in 4.4.5 in which eager-loaded related elements were not being tracked on cached pages ([#514](https://github.com/putyourlightson/craft-blitz/issues/514)).

## 4.4.5 - 2023-05-25

> {warning} To ensure the fix is applied, the cache should be cleared or refreshed after this update completes.

### Fixed

- Fixed a bug in which eager-loaded custom fields were not being tracked on cached pages ([#507](https://github.com/putyourlightson/craft-blitz/issues/507)).

## 4.4.4 - 2023-03-27

### Fixed

- Fixed a bug in which uninstalling the plugin could throw an error ([#490](https://github.com/putyourlightson/craft-blitz/issues/490)).

## 4.4.3 - 2023-03-14

### Fixed

- Fixed a bug in which cached pages were not being deleted for disabled elements or error pages with the “Expire the cache and regenerate in a queue job” refresh mode selected ([#483](https://github.com/putyourlightson/craft-blitz/issues/483)).

## 4.4.2 - 2023-03-09

### Fixed

- Fixed a bug in which the wrong instance of `StringHelper` was being used ([#481](https://github.com/putyourlightson/craft-blitz/issues/481)).

## 4.4.1 - 2023-03-07

### Fixed

- Fixed the `generatePageBasedOnQueryString` check and ensured that cached includes are always allowed.
- Fixed usage of the `Html::svg()` method, which was only added in Craft 4.3.0 ([#480](https://github.com/putyourlightson/craft-blitz/issues/480)).

## 4.4.0 - 2023-03-01

> {warning} Tracking of attributes and custom fields takes place when pages are cached, therefore it is important to clear or refresh the cache after this update completes.

### Added

- Added detection of which attributes and custom fields are changed on each element save.
- Added tracking of which custom fields are output per element per page, greatly reducing the number of cached pages that must be invalidated when content changes ([#465](https://github.com/putyourlightson/craft-blitz/issues/465)).
- Added tracking of which attributes and custom fields are used by each element query, greatly reducing the number of element queries that must be executed during the cache refresh process ([#466](https://github.com/putyourlightson/craft-blitz/issues/466)).
- Added purging of asset image URLs and existing image transforms when image dimensions or focal points are changed.
- Added a `purgeAssetImagesWhenChanged` config setting that determines whether asset images should be purged when changed.
- Added a tip about excluding the cache folder path from search engine indexing to the Blitz File Storage settings.

### Changed

- Cookies are now removed from cached responses as they can prevent edge-side caching.
- Renamed the `cacheElements` config setting and page specific option to `trackElements`.
- Renamed the `cacheElementQueries` config setting and page specific option to `trackElementQueries`.
- Reverted the removal of the `generatePagesWithQueryStringParams` config setting.
- Cached pages are now generated in a more deterministic order, by URI ascending.
- The Local Generator now catches context panic errors ([#476](https://github.com/putyourlightson/craft-blitz/issues/476)).

### Fixed

- Fixed the `rewrite.php` file not detecting the `ENVIRONMENT` environment variable.

### Deprecated

- Deprecated the `cacheElements` config setting. Use `trackElements` instead.
- Deprecated the `cacheElementQueries` config setting. Use `trackElementQueries` instead.
- Deprecated the `craft.blitz.options.cacheElements()` template variable. Use `craft.blitz.options.trackElements()` instead.
- Deprecated the `craft.blitz.options.cacheElementQueries()` template variable. Use `craft.blitz.options.trackElementQueries()` instead.

## 4.3.3 - 2023-02-14

### Changed

- The `SSI Enabled` tip now also displays whether Server-Side Includes (SSI) are not enabled on the web server.

## 4.3.2 - 2023-02-13

### Added

- Added a tip to the `SSI Enabled` setting that appears if Server-Side Includes (SSI) are enabled on the web server.

## 4.3.1 - 2023-02-10

### Fixed

- Fixed a bug in which saving elements without going through a draft were not triggering cache refreshes ([#474](https://github.com/putyourlightson/craft-blitz/issues/474)).

## 4.3.0 - 2023-02-07

### Added

- Added a Blitz Cache dashboard widget with actions to refresh specific pages, sites or the entire cache.
- Added a `rewrite.php` file that can be used in situations where a server rewrite is not possible.
- Added the `craft.blitz.includeCached()` template variable, that includes a cached template using SSI or ESI if enabled, otherwise via an AJAX request.
- Added the `craft.blitz.includeDynamic()` template variable, that includes a dynamically rendered template via an AJAX request.
- Added the `craft.blitz.fetchUri()` template variable, that fetches a URI via an AJAX request. Whether the URI response is cached or not is determined by the URI patterns in the plugin settings.
- Added a `SSI Enabled` setting that enables Blitz to include templates using Server-Side Includes (SSI), which must be enabled on the web server.
- Added a `ESI Enabled` setting that enables Blitz to include templates using Edge-Side Includes (ESI), which must be enabled on the web server or reverse proxy (CDN).
- Added a “Cached Includes” column to the Blitz cache utility for the File Cache Storage driver.
- Added a `timeout` config setting to the HTTP Generator ([#467](https://github.com/putyourlightson/craft-blitz/issues/467)).

### Changed

- Improved the detection of when elements should be refreshed based on changes.
- Improved the performance of refresh job requests when cache generation is disabled ([#456](https://github.com/putyourlightson/craft-blitz/issues/456)).
- Generator, deployer and purger jobs are now released before refreshing the entire cache, provided cache clearing is enabled ([#454](https://github.com/putyourlightson/craft-blitz/issues/454)).
- Changed the default authentication method for the Cloudflare purger to “API token” and improved the field instruction text.
- Replaced the abandoned `symplify/git-wrapper` package with `cypresslab/gitelephant`.
- Increased the default timeout of HTTP Generator requests to 120 seconds ([#467](https://github.com/putyourlightson/craft-blitz/issues/467)).

### Fixed

- Fixed a bug in which the “Served by Blitz” comment was not respecting the page specific options in the first request ([#459](https://github.com/putyourlightson/craft-blitz/issues/459)).
- Fixed a bug in which the Blitz Cache utility could throw an error if the `cacheStorageSettings['countCachedFiles']` config setting was disabled.
- Fixed a bug in which calling `hasSales()` on a Commerce variant query could throw an error ([#471](https://github.com/putyourlightson/craft-blitz/issues/471)).

### Deprecated

- Deprecated the `craft.blitz.getTemplate()` template variable. Use `craft.blitz.includeCached()` or `craft.blitz.includeDynamic()` instead.
- Deprecated the `craft.blitz.getUri()` template variable. Use `craft.blitz.fetchUri()` instead.
- Deprecated the `blitz/templates/get` controller action.

## 4.2.3 - 2022-10-19

### Fixed

- Fixed a bug in which one-time use tokens would not work with Blitz enabled ([#448](https://github.com/putyourlightson/craft-blitz/issues/448)).

## 4.2.2 - 2022-09-26

### Changed

- The Local Generator now continues generating pages, rather than failing, even when Twig template errors are encountered ([#444](https://github.com/putyourlightson/craft-blitz/issues/444)).
- The Git Deployer now only appends `/index.html` to site URIs with HTML mime types ([#443](https://github.com/putyourlightson/craft-blitz/issues/443)).

## 4.2.1 - 2022-07-21

### Fixed

- Fixed an issue with the Local Generator when Twig extensions were being registered via a module ([#437](https://github.com/putyourlightson/craft-blitz/issues/437)).

## 4.2.0 - 2022-07-05

### Added

- Added a Commerce plugin integration that refreshes variants on order completion so that their stock is updated ([#432](https://github.com/putyourlightson/craft-blitz/issues/432)).

### Changed

- The cache is now refreshed when the focal point of an asset is changed ([#431](https://github.com/putyourlightson/craft-blitz/issues/431)).

## 4.1.4 - 2022-06-21

### Changed

- Exceptions are caught and logged, rather than being thrown, during cache generation using the HTTP Generator ([#418](https://github.com/putyourlightson/craft-blitz/issues/418)).

### Fixed

- Fixed an issue in which expiry dates were not being added or updated for pending entries ([#422](https://github.com/putyourlightson/craft-blitz/issues/422)).

## 4.1.3 - 2022-05-23

### Fixed

- Fixed issues with Apache server rewrites that could prevent pages from being cached ([#411](https://github.com/putyourlightson/craft-blitz/issues/411)).

## 4.1.2 - 2022-05-17

### Added

- Added the Blitz Hints announcement to the dashboard.

## 4.1.1 - 2022-05-16

### Changed

- Bumped the required version of the Blitz Hints package.

## 4.1.0 - 2022-05-16

### Added

- Added a utility that provides template performance hints, powered by the [Blitz Hints](https://github.com/putyourlightson/craft-blitz-hints) package, [read the announcement](https://putyourlightson.com/articles/ballroom-blitz).

### Fixed

- Fixed the Local Generator bootstrap process for older Craft installations ([#404](https://github.com/putyourlightson/craft-blitz/issues/404)).

## 4.0.3 - 2022-05-06

### Fixed

- Fixed a bug in a migration when no cache purger settings existed ([#402](https://github.com/putyourlightson/craft-blitz/issues/402)).

## 4.0.2 - 2022-05-05

### Fixed

- Fixed a bug in a migration when no cache purger settings existed ([#402](https://github.com/putyourlightson/craft-blitz/issues/402)).

## 4.0.1 - 2022-05-05

### Fixed

- Fixed a bug in the `purge` console command.
- Fixed a bug in the custom log target.

## 4.0.0 - 2022-05-04

> {warning} Cache warmers have been completely replaced by cache generators. The included/excluded query string parameters config setting format has changed. See the new formats [here](https://github.com/putyourlightson/craft-blitz/blob/v4/src/config.php).

### Added

- Added compatibility with Craft 4.
- Added a new `Refresh Mode` setting that determines when and how the cache should be refreshed.
- Added the concept of cache generation, that supersedes cache warming, and is used both for generating, regenerating and in some cases removing cached pages.
- Added the ability to revalidate cached pages that have expired when serving cached responses ([#381](https://github.com/putyourlightson/craft-blitz/issues/381)).
- Added the included/excluded query string parameter settings to the Advanced Settings tab and added the ability for them to be site-specific.
- Added the ability for cache purgers to be run in queue jobs.
- Added a new `refreshCacheWhenElementMovedInStructure` config setting, defaulting to `true`, that controls whether the cache should be refreshed when an element is moved within a structure ([#289](https://github.com/putyourlightson/craft-blitz/issues/289)).
- Added a new `cacheNonHtmlResponses` config setting, defaulting to `false`, that allows enabling caching of pages that return non-HTML responses.

### Changed

- Replaced all `Warmer` drivers and classes with `Generator` drivers and classes.
- Replaced the `Guzzle Warmer` with the `HTTP Generator`.
- Replaced the `Local Warmer` (experimental) with the `Local Generator` (stable).
- Replaced the `Log To File` helper package with a custom Monolog log target.
- Changed the included/excluded query string parameters config setting format, see the new format [here](https://github.com/putyourlightson/craft-blitz/blob/v4/src/config.php).

### Removed

- Removed the `Clear Cache Automatically` and `Warm Cache Automatically` settings (use the `Refresh Mode` setting instead).
- Removed the `Warm Cache Delay` setting on cache purgers.
- Removed the `warmCacheDelay` property from the `CachePurgerTrait` class.
- Removed the `delay` property from the `DriverJob` class.
- Removed the `delay` parameter from all methods in the `CacheWarmerInterface` class.
