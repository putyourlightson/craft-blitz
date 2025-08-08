# Release Notes for Blitz

## 5.12.2 - Unreleased

- Improved the handling of cacheable action requests.
- Improved the tracked actions view in the Blitz Diagnostics utility.
- Fixed a bug in which an error could be thrown when viewing nested content block elements in the Blitz diagnostics utility ([#828](https://github.com/putyourlightson/craft-blitz/issues/828)).
- Fixed a bug in which cached includes were not being cached when included in non-cacheable pages ([#829](https://github.com/putyourlightson/craft-blitz/issues/829)).

## 5.12.1 - 2025-08-02

- Added a `cachedIncludePathParam` config setting that determines the query string path parameter to use for cached includes.

## 5.12.0 - 2025-08-01

> [!NOTE]
> The cache must be cleared _and_ flushed after this update completes.

- Added “Basic” HTTP authentication support to the HTTP Generator ([#821](https://github.com/putyourlightson/craft-blitz/issues/821)).
- Added template, code and backtrace info to tracked element queries in the Blitz diagnostics utility.
- Refactored how cached include URIs are generated to help prevent issues with encoded slashes in URLs ([#823](https://github.com/putyourlightson/craft-blitz/issues/823)).
- Cached includes are no longer cached when the debug toolbar is enabled ([#822](https://github.com/putyourlightson/craft-blitz/issues/822)).
- Cached includes are no longer cached when called via a preview URL ([#804](https://github.com/putyourlightson/craft-blitz/issues/804)).
- Eager-loaded relation field queries are now explicitly excluded from tracking ([#812](https://github.com/putyourlightson/craft-blitz/issues/812), [#817](https://github.com/putyourlightson/craft-blitz/issues/817)).
- Fixed a bug in which assets in local filesystems without public URLs were causing refresh cache jobs to fail ([#819](https://github.com/putyourlightson/craft-blitz/issues/819)).
- Fixed a bug in which URLs with site tokens could be cached.

## 5.11.5 - 2025-06-16

- URIs with period-only segments are no longer cached.
- Fixed a bug in which “cached by Blitz” comments were not being output.
- Fixed a bug in which allowed query string params were not being applied when query string caching was set to `Do not cache URLs with query strings` ([#815](https://github.com/putyourlightson/craft-blitz/issues/815)).

## 5.11.4 - 2025-06-03

- Nested element queries are now distinguished in the Blitz diagnostics report.
- Duplicate foreign keys and indexes are now removed before performing database migrations.

## 5.11.3 - 2025-05-21

- Fixed a bug in which the response content could be empty when disabling caching using page specific options ([#808](https://github.com/putyourlightson/craft-blitz/issues/808)).

## 5.11.2 - 2025-05-20

- Fixed the resetting of auto increment values when using Postgres ([#805](https://github.com/putyourlightson/craft-blitz/issues/805)).

## 5.11.1 - 2025-05-19

- Improved checks for cacheable action requests.

## 5.11.0 - 2025-05-19

- Added a [Datastar](https://putyourlightson.com/plugins/datastar) integration that makes it possible to cache and serve streamed responses.
- Added a `cacheActionRequests` setting that determines whether action requests can be cached.
- Added tracked actions to the Blitz Diagnostics utility.
- Fixed a bug in which an exception could be thrown after Craft Commerce checkout when the order contained one or more custom line items ([#799](https://github.com/putyourlightson/craft-blitz/issues/799)).

## 5.10.5 - 2025-04-28

> [!NOTE]
> The cache should be cleared or refreshed after this update completes.

- Increased the Cloudflare API limit on purge requests to 100 URLs per request.
- Fixed triggering cache refreshes after moving assets between folders ([#784](https://github.com/putyourlightson/craft-blitz/issues/784)).
- Fixed a rendering issue in the tracked query string params section of the Blitz Diagnostics utility ([#790](https://github.com/putyourlightson/craft-blitz/issues/790)).
- Fixed an issue in which relation fields were resulting in element queries being inadvertently tracked.

## 5.10.4 - 2025-04-16

- Fixed a bug in which an exception could be thrown when tracking eager-loaded element queries ([#789](https://github.com/putyourlightson/craft-blitz/issues/789)).

## 5.10.3 - 2025-04-13

- Moving assets between folders now triggers a cache refresh ([#784](https://github.com/putyourlightson/craft-blitz/issues/784)).
- Reverted the minimum plugin version requirement back to 4.x to help ease upgrading.

## 5.10.2 - 2025-04-09

### Added

- Added a `refreshExpiredCacheAfterVisit` config setting that determines whether expired cached pages should be refreshed after being visited by a user.

## 5.10.1 - 2025-04-08

### Added

- Added a `getCacheableSiteUri` event that makes it possible to modify the site URI that Blitz reads from the request.

### Fixed

- Fixed a bug in which the tracking of eager-loaded elements was causing an infinite loop ([#785](https://github.com/putyourlightson/craft-blitz/issues/785)).

## 5.10.0 - 2025-04-07

### Added

- Added support for viewing plugin settings when `allowAdminChanges` is disabled ([#770](https://github.com/putyourlightson/craft-blitz/issues/770)).

### Changed

- Blitz now requires Craft CMS 5.6.0.
- Improved the tracking of eager-loaded fields.
- Improved the tracking of disabled entries in nested and relation field queries.
- Eager-loading params are now explicitly excluded from element query params ([#772](https://github.com/putyourlightson/craft-blitz/issues/772)).

### Removed

- Removed the Blitz Hints utility.

## 5.9.12 - 2025-03-12

### Fixed

- Fixed a bug in which the homepage was not being refreshed when only a Neo field was modified ([#754](https://github.com/putyourlightson/craft-blitz/issues/754)).

## 5.9.11 - 2025-03-06

### Changed

- The sidebar panel is now only visible to users with explicit permission to view it.

### Fixed

- Fixed a bug in which cache files with decoded characters were not being saved ([#767](https://github.com/putyourlightson/craft-blitz/issues/767)).

## 5.9.10 - 2025-01-30

### Changed

- Failed cache generated pages are now more explicitly logged when verbose mode is used.

### Fixed

- Fixed a bug that was throwing an exception when the Redis queue driver was being used ([#752](https://github.com/putyourlightson/craft-blitz/issues/752)).

## 5.9.9 - 2024-12-31

### Changed

- Slashes in inject script params are now decoded to prevent errors when the `AllowEncodedSlashes` directive is disabled.

## 5.9.8 - 2024-12-10

### Changed

- The reasons why pages failed to be generated using the Local Generator are now logged when debug mode is enabled ([#737](https://github.com/putyourlightson/craft-blitz/issues/737)).

## 5.9.7 - 2024-12-06

### Changed

- More deployer settings are now redacted when generating a diagnostics report.
- The existence of cached files is now checked before deletion, to prevent unnecessarily logged warnings ([#741](https://github.com/putyourlightson/craft-blitz/issues/741)).
- The `injectScriptEvent` variable is no longer defined in the global scope, for better compatibility with JavaScript libraries ([#747](https://github.com/putyourlightson/craft-blitz/issues/747)).

## 5.9.6 - 2024-11-15

### Fixed

- Fixed a bug that was preventing cache generation when “Do not cache URLs with query strings” was selected ([#733](https://github.com/putyourlightson/craft-blitz/issues/733)).

## 5.9.5 - 2024-11-13

### Fixed

- Fixed a bug that was preventing cache generation when the “Only Cache Lowercase URIs” setting was enabled.

## 5.9.4 - 2024-11-13

### Fixed

- Fixed a bug introduced in version 5.9.3 that broke cache generation when “Do not cache URLs with query strings” was selected ([#733](https://github.com/putyourlightson/craft-blitz/issues/733)).

## 5.9.3 - 2024-11-07

### Fixed

- Fixed a bug in which pages with query strings in their URLs could be cached even when “Do not cache URLs with query strings” was selected ([#729](https://github.com/putyourlightson/craft-blitz/issues/729)).

## 5.9.2 - 2024-11-04

### Fixed

- Fixed an error that could occur when generating tagged caches in some cases ([#728](https://github.com/putyourlightson/craft-blitz/issues/728)).
- Fixed a bug in which refresh jobs were not being created immediately in some console commands.

## 5.9.1 - 2024-10-22

### Changed

- The element sidebar panel is no longer displayed when no storage driver is selected ([#718](https://github.com/putyourlightson/craft-blitz/issues/718)).
- All inject script events are now called on the `document` element except for `load`, which is called on the `window` element ([#721](https://github.com/putyourlightson/craft-blitz/issues/721)).

### Fixed

- Fixed a bug on the Tracked Element Queries page in the Blitz Diagnostics utility ([#716](https://github.com/putyourlightson/craft-blitz/issues/716)).
- Fixed a bug in the Blitz Diagnostics utility that could throw an error if a custom field existed with the handle `displayName` ([#723](https://github.com/putyourlightson/craft-blitz/issues/723)).
- Fixed a bug in the Blitz Diagnostics utility when using a Postgres database ([#724](https://github.com/putyourlightson/craft-blitz/issues/724)).

## 5.9.0 - 2024-10-04

### Added

- Added the ability to ignore hints in the Blitz Hints utility ([#714](https://github.com/putyourlightson/craft-blitz/issues/714)).

### Changed

- CSRF tokens are now only loaded via a script in non-Sprig requests.

### Fixed

- Fixed a bug that was preventing the organic regeneration of expired cached pages.

## 5.8.1 - 2024-10-01

### Fixed

- Fixed an error that could occur when updating to 5.8.0.

## 5.8.0 - 2024-10-01

### Added

- Added the `onlyCacheLowercaseUris` plugin setting.
- Added `enabled` lightswitches to the URI pattern and query string parameter settings.

### Changed

- The `injectScriptEvent` event is now called on the `window` element instead of `document`.
- Increased the batch size used when flushing the entire cache.

### Fixed

- Fixed some styling issues in the Blitz Diagnostics utility.
- Fixed false positives from appearing in the Blitz Hints utility when querying entry authors ([#710](https://github.com/putyourlightson/craft-blitz/issues/710)).

## 5.7.1 - 2024-08-26

### Fixed

- Fixed a bug in which installing the plugin via the CLI could cause an error ([#705](https://github.com/putyourlightson/craft-blitz/issues/705)).

## 5.7.0 - 2024-08-26 [CRITICAL]

> [!WARNING]
> This update includes a fix for an issue in which Blitz could send incorrect Cache-Control headers. Please [read this article](https://putyourlightson.com/articles/critical-update-for-a-blitz-blunder) to find out whether the issue affects your site, and what you should do. To ensure the changes in this update are applied, the cache should be refreshed after this update completes.

### Added

- Added a check for whether the cache should be refreshed after every request has ended, meaning that setting the `RefreshCacheService::batchMode` property no longer serves a purposes and can be safely removed.
- Added compatibility with Craft 5.3.0 for detecting eager-loading opportunities in the Blitz Hints utility.

### Changed

- The expiry date displayed in the element sidebar panel now reflects the entry’s expiry date, if set and sooner than the cached page’s expiry date ([#698](https://github.com/putyourlightson/craft-blitz/issues/698)).
- The `refreshCacheEnabled` config setting is now actually respected.

### Fixed

- Fixed the default cache control header values that were inadvertently set to incorrect values ([learn more](https://putyourlightson.com/articles/critical-update-for-a-blitz-blunder)).
- Fixed the nested element type count displayed in the Blitz Diagnostics utility.
- Fixed a bug in which the date cached and expiry dates were not being displayed in the correct timezone in the element sidebar panel ([#698](https://github.com/putyourlightson/craft-blitz/issues/698)).
- Fixed a bug in which the homepage was not being displayed as cached in the element sidebar panel.
- Fixed a bug that was causing integrity constraint violation errors to be logged ([#699](https://github.com/putyourlightson/craft-blitz/issues/699)).

### Deprecated

- Deprecated the `RefreshCacheService::batchMode` property.

## 5.6.4 - 2024-08-15

> [!NOTE]
> The cache should be cleared or refreshed after this update completes.

### Changed

- Recreated some database tables to ensure that composite primary keys are correctly created.

## 5.6.3 - 2024-08-15

### Fixed

- Fixed an exception that could be thrown during database migrations when using MariaDB ([#693](https://github.com/putyourlightson/craft-blitz/issues/693)).

## 5.6.2 - 2024-08-05

### Fixed

- Fixed a bug that could throw an exception when viewing tracked entries in the Blitz Diagnostics utility when the database tables have a prefix.
- Fixed the dropping of a foreign key in a database migration ([#693](https://github.com/putyourlightson/craft-blitz/issues/693)).

## 5.6.1 - 2024-08-05

### Fixed

- Fixed a bug that could throw an exception when viewing tracked entries in the Blitz Diagnostics utility when the database tables have a prefix.

## 5.6.0 - 2024-08-05

> [!NOTE]
> For the cache and expiry dates to appear in the new sidebar panel, the cache should be cleared or refreshed after this update completes.

### Added

- Added a sidebar panel to element edit pages ([#690](https://github.com/putyourlightson/craft-blitz/issues/690)).
- Added a `dateCached` column to cache records which is output in the sidebar panel and the Blitz Diagnostics utility.
- Added the ability to track eager-loaded relation fields nested inside matrix blocks ([#657](https://github.com/putyourlightson/craft-blitz/issues/657)).
- Added a structure view to tracked nested element pages in the Blitz Diagnostics utility.

### Changed

- The `craft.blitz.csrfInput()`, `craft.blitz.csrfParam()` and `craft.blitz.csrfToken()` functions now output inline values rather than inject scripts when called via AJAX requests.
- The Commerce integration now only refreshes product variants if their inventory is tracked.

## 5.5.1 - 2024-07-23

### Changed

- Nested element types are now differentiated in the Blitz Diagnostics utility.

### Fixed

- Fixed a bug in which the plugin install migration could throw an exception in version 5.5.0 ([#688](https://github.com/putyourlightson/craft-blitz/issues/688)).

## 5.5.0 - 2024-07-22

> [!IMPORTANT]
> To ensure the changes are applied, the cache should be cleared or refreshed after this update completes.

### Added

- Added the ability for Blitz to track custom field instances with renamed handles ([#682](https://github.com/putyourlightson/craft-blitz/issues/682)).
- Added the ability to view which tags are being tracked by each page in the Blitz Diagnostics utility.
- Added the ability to view which pages/includes are tracking each element in the Blitz Diagnostics utility.

### Changed

- The “Served by Blitz” comment is now also output when the cached output is initially created and served.
- Batch mode is now enabled whenever elements are resaved via a queue job.
- Archived and deleted elements are no longer tracked when populated via eager-loaded element queries.
- Criteria defined in eager-loaded element query mappings are now respected when tracking elements.
- Updated links to Craft documentation to use the 5.x version.

### Fixed

- Fixed a bug in which the presence of legacy File Storage settings in project config was throwing errors when upgrading from Blitz 4 ([#668](https://github.com/putyourlightson/craft-blitz/issues/668)).
- Fixed a bug in which the failed site count was not being correctly displayed in the Blitz Diagnostics recommendations.

## 5.4.0 - 2024-07-04

### Added

- Added the ability for element site status changes to be tracked while not refreshing propagating elements ([#631](https://github.com/putyourlightson/craft-blitz/issues/631)).

## 5.3.4 - 2024-07-03

### Fixed

- Fixed a bug in which the cached page count of sites that contained the paths of other sites could be inaccurately displayed in the Blitz Cache utility.

## 5.3.3 - 2024-06-27

### Fixed

- Fixed a bug in which the priority of refresh cache and driver jobs was interpreted as `0` when set to `null` ([#655](https://github.com/putyourlightson/craft-blitz/issues/655)).
- Fixed an issue in which the priority of batch jobs could be a negative number and therefore jobs would never complete.

## 5.3.2 - 2024-06-18

### Fixed

- Fixed a bug in which modules that were not bootstrapped were throwing an error when generating a report in the Blitz Diagnostics utility ([#668](https://github.com/putyourlightson/craft-blitz/issues/668)).
- Fixed a bug in which incorrect purge requests were being sent to CloudFront for the homepage ([#673](https://github.com/putyourlightson/craft-blitz/issues/673)).

## 5.3.1 - 2024-05-16

### Added

- Added a tracked fields column to the tracked elements page in the Blitz Diagnostics utility.

### Changed

- Sites in the Blitz diagnostics report are now sorted by ID in ascending order.

### Fixed

- Fixed a bug in the Git Deployer that was throwing an error when a cached page no longer existed ([#664](https://github.com/putyourlightson/craft-blitz/issues/664)).

## 5.3.0 - 2024-05-07

### Added

- Added anonymised site names to the Blitz diagnostics report.
- Added a detailed breakdown of element types to the Blitz diagnostics report.
- Added the ability to download the Blitz diagnostics report as a markdown file.

### Changed

- Optimised the refresh cache process by excluding redundantly tracked element queries based on their limit and offset values.

### Fixed

- Fixed the check for whether the `blitz/cache/refresh-expired` console command was executed within the past 24 hours.
- Fixed diagnostics notifications in the control panel.
- Fixed the detection of lazy eager-loaded queries.
- Fixed tracking of some element query attributes.

## 5.2.0 - 2024-04-27

### Added

- Added the ability to generate a diagnostics report in the Blitz Diagnostics utility, that can be shared when requesting support.

## 5.1.6 - 2024-04-26

### Fixed

- Fixed bug in the SQL statement introduced in 5.1.5 when using a Postgres database.

## 5.1.5 - 2024-04-26

### Changed

- Improved the deletion of cache records during the refresh cache process to help avoid database memory issues.

## 5.1.4 - 2024-04-22

### Changed

The `blitz/cache/refresh-cache-tags` and
`blitz/cache/refresh-expired-elements` no longer forcibly generate the cache.

## 5.1.3 - 2024-04-13

### Changed

- Reverted back to listening for resave and propagate element events.

## 5.1.2 - 2024-04-12

### Changed

- Dynamic includes in preview requests are now also sent via AJAX, passing through the token param ([#653](https://github.com/putyourlightson/craft-blitz/issues/653)).

### Fixed

- Fixed a bug in which propagated saves were not triggering refresh cache jobs ([#654](https://github.com/putyourlightson/craft-blitz/issues/654)).

## 5.1.1 - 2024-04-12

### Fixed

- Fixed a bug in which the Blitz Diagnostic utility could throw an error when viewing tracked includes and when using Postgres ([#653](https://github.com/putyourlightson/craft-blitz/issues/653)).
- Fixed an edge-case bug in which cached includes were not being refreshed when expired in a multi-site setup using subfolders.

## 5.1.0 - 2024-04-10

### Added

- Added tracked includes to the Blitz Diagnostics utility.

### Fixed

- Fixed a bug in which cached includes were not being refreshed when a URL was provided.
- Fixed an edge-case bug in which cached includes were not being refreshed when expired.

## 5.0.0 - 2024-04-07

### Added

- Added compatibility with Craft 5.
- Added suggesting the use of the `eagerly()` function to lazy-loaded element queries in the Blitz Hints utility.

### Changed

- The Blitz Hints utility is now powered by Sprig, no longer tracks route variable hints and no longer requires an external package.

### Removed

- Removed the `craft.blitz.getTemplate()` template variable. Use `craft.blitz.includeCached()` or `craft.blitz.includeDynamic()` instead.
- Removed the `craft.blitz.getUri()` template variable. Use `craft.blitz.fetchUri()` instead.
- Removed the `blitz/templates/get` controller action.
- Removed the `cacheElements` config setting. Use `trackElements` instead.
- Removed the `cacheElementQueries` config setting. Use `trackElementQueries` instead.
- Removed the `craft.blitz.options.cacheElements()` template variable. Use `craft.blitz.options.trackElements()` instead.
- Removed the `craft.blitz.options.cacheElementQueries()` template variable. Use `craft.blitz.options.trackElementQueries()` instead.
- Removed the `createGzipFiles` setting.
- Removed the `createBrotliFiles` setting.
- Removed the `BlitzVariable::CACHED_INCLUDE_ACTION` constant. Use `CacheRequestService::CACHED_INCLUDE_ACTION` instead.
- Removed the `BlitzVariable::DYNAMIC_INCLUDE_ACTION` constant. Use `CacheRequestService::DYNAMIC_INCLUDE_ACTION` instead.
- Removed the `ElementTypeHelper::LIVE_STATUSES` constant.
- Removed the `SettingsModel::clearOnRefresh` method. Use `SettingsModel::shouldClearOnRefresh` instead.
- Removed the `SettingsModel::expireOnRefresh` method. Use `SettingsModel::shouldExpireOnRefresh` instead.
- Removed the `SettingsModel::generateOnRefresh` method. Use `SettingsModel::shouldGenerateOnRefresh` instead.
- Removed the `SettingsModel::purgeAfterRefresh` method. Use `SettingsModel::shouldPurgeAfterRefresh` instead.
- Removed the `SettingsModel::generatePageBasedOnQueryString` method. Use `SettingsModel::shouldGeneratePageBasedOnQueryString` instead.
- Removed the `SettingsModel::purgeAssetImages` method. Use `SettingsModel::shouldPurgeAssetImages` instead.
