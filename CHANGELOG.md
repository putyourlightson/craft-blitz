# Release Notes for Blitz

## 3.10.3 - 2021-09-05
### Changed
- Improved the detection of whether a new entry was created in Craft CMS version 3.7.5 and above.

### Security
- Query string paths are now validated to be at least one level deep if URLs with query strings are set to be cached as unique pages ([#343](https://github.com/putyourlightson/craft-blitz/issues/343)).

## 3.10.2 - 2021-07-21
### Fixed
- Fixed the cache not being refreshed when a new entry was created with Craft CMS version 3.7.x installed.

## 3.10.1 - 2021-07-19
### Fixed
- Fixed a database migration error that could occur when Craft CMS is updated to version 3.7.0 before Blitz’s own migrations have run ([#332](https://github.com/putyourlightson/craft-blitz/issues/332)).

## 3.10.0 - 2021-07-05
### Added
- Added `paginate` as a page specific option for automating the warming of paginated URIs ([#319](https://github.com/putyourlightson/craft-blitz/issues/319)).
- Added user permissions for the refresh site utility ([#214](https://github.com/putyourlightson/craft-blitz/issues/214)).
- Added the `includedQueryStringParams` config setting that compares query string params using regular expressions ([#326](https://github.com/putyourlightson/craft-blitz/issues/326)).
- Added the `warmPagesWithQueryStringParams` config setting that determines whether pages containing query string parameters should be warmed ([#326](https://github.com/putyourlightson/craft-blitz/issues/326)).

### Changed
- Front-end API requests now run the queue so that actions complete fully.

### Fixed
- Fixed a bug in which expired entries were not being refreshed ([#320](https://github.com/putyourlightson/craft-blitz/issues/320)).

## 3.9.0 - 2021-05-03
### Added
- Added the ability to refresh a cached site with the new `Refresh Site` utility and `refresh-site` console command ([#214](https://github.com/putyourlightson/craft-blitz/issues/214)).
- Added the `isCacheableRequest` event to `CacheRequestService` ([#306](https://github.com/putyourlightson/craft-blitz/issues/306)).
- Added the `queueJobTtr` and `maxRetryAttempts` config settings ([#308](https://github.com/putyourlightson/craft-blitz/issues/308)).

### Changed
- Improved console command messaging when a component is disabled.
- Changed the token request parameter check to use the value defined in the general config settings ([#307](https://github.com/putyourlightson/craft-blitz/issues/307)).
- The site is now determined by matching the site with the longest base URL that matches a provided URL ([#309](https://github.com/putyourlightson/craft-blitz/issues/309), [#310](https://github.com/putyourlightson/craft-blitz/issues/310)).

### Fixed
- Fixed an error that could occur when an empty response was encountered with the local warmer ([#305](https://github.com/putyourlightson/craft-blitz/issues/305)).

## 3.8.0 - 2021-03-16
### Added
- Added a `Create Brotli File` setting to the File Storage driver ([#297](https://github.com/putyourlightson/craft-blitz/issues/297)).
- Added the ability to use `*` as a wildcard in the `Refresh Cached URLs` utility and `refresh-urls` console command ([#300](https://github.com/putyourlightson/craft-blitz/issues/300)).

### Changed
- The values in the `excludedQueryStringParams` are now compared to query string params using regular expressions ([#272](https://github.com/putyourlightson/craft-blitz/issues/272)).
- The check for a cacheable request is now done inside the `Application::EVENT_INIT` event ([#299](https://github.com/putyourlightson/craft-blitz/issues/299)).

### Fixed
- Fixed an error that could occur when running refresh cache jobs with Craft 3.3.16 and below ([#285](https://github.com/putyourlightson/craft-blitz/issues/285)).
- Fixed a bug that was throwing an error message if the resource base URL was set to the site URL ([#287](https://github.com/putyourlightson/craft-blitz/issues/287)).
- Fixed a bug in which the `queue` flag was not being respected in console commands ([#294](https://github.com/putyourlightson/craft-blitz/issues/294)).

## 3.7.0 - 2021-01-05
> {tip} This update optimises the performance of refresh cache jobs. The cache should be manually refreshed following the update in order to get the full performance benefits.

### Added
- Added compatibility with Solspace Calendar and other plugins that use joins in element queries.
- Added an indication of how many pages were not warmed, if any, in console commands ([#221](https://github.com/putyourlightson/craft-blitz/issues/221)).
- Added the ability to disable the warm cache utility by setting the cache warmer to “None” ([#275](https://github.com/putyourlightson/craft-blitz/issues/275)).

### Changed
- The deployer now removes files when pages are cleared from the cache ([#218](https://github.com/putyourlightson/craft-blitz/issues/218)).
- The value of the `X-Powered-By` header is now appended, rather than added.
- Optimised cached element queries. 

### Fixed
- Fixed a bug in which expired entries were not being cleared ([#267](https://github.com/putyourlightson/craft-blitz/issues/267)).

## 3.6.12 - 2020-11-10
### Added
- Added the API token authentication method to the Cloudflare purger ([#263](https://github.com/putyourlightson/craft-blitz/issues/263)).
- Added a site URI model to the ResponseEvent class.

### Fixed
- Fixed an error that could occur if the CP trigger was set to `null` ([#264](https://github.com/putyourlightson/craft-blitz/issues/264)).

## 3.6.11 - 2020-10-08
### Added
- Added a `--queue` flag that can be used with some console commands to cause job to be queued but not run ([#260](https://github.com/putyourlightson/craft-blitz/issues/260)).

### Fixed
- Fixed an error that could occur if URI patterns contained escaped delimiter characters ([#261](https://github.com/putyourlightson/craft-blitz/issues/261)).

## 3.6.10 - 2020-09-23
### Fixed
- Fixed a bug introduced in version 3.6.9 that could prevent URL patterns being matched.

## 3.6.9 - 2020-09-23
### Added
- Added the `countCachedFiles` config setting to the file storage driver.

### Fixed
- Fixed a bug in which cached files containing encoded characters would not be cleared correctly ([#252](https://github.com/putyourlightson/craft-blitz/issues/252)).
- Fixed a bug in which cached pages were not being correctly refreshed by the Yii Cache Storage driver ([#257](https://github.com/putyourlightson/craft-blitz/issues/257)).
- Fixed a bug in the base site URL replacement logic ([#258](https://github.com/putyourlightson/craft-blitz/pull/258)).
- Fixed hard-coded path param in check for cacheable request.

## 3.6.8 - 2020-08-26
### Added
- Added `outputComments` as a page specific option ([#243](https://github.com/putyourlightson/craft-blitz/issues/243)).

### Fixed
- Fixed Guzzle warmer delay that was hard-coded to 5 seconds.

### Changed
- Cache IDs and elements are now reset after a refresh cache job is created for them ([#245](https://github.com/putyourlightson/craft-blitz/issues/245)).
- Improved primary key migration introduced in version 3.6.6 ([#240](https://github.com/putyourlightson/craft-blitz/issues/240)).

## 3.6.7 - 2020-08-17
### Added
- Added a debug message if no site URI could be determined from the requested URL.

### Fixed
- Fixed an edge case migration error that could occur when updating to version 3.6.6 ([#240](https://github.com/putyourlightson/craft-blitz/issues/240)).
- Fixed a bug which could prevent element listing pages from being refreshed when an element was deleted ([#241](https://github.com/putyourlightson/craft-blitz/issues/241)).

## 3.6.6 - 2020-07-27
### Changed
- Added the ability to override site URIs before they are warmed ([#230](https://github.com/putyourlightson/craft-blitz/issues/230)).
- Added primary keys to all database tables ([#237](https://github.com/putyourlightson/craft-blitz/issues/237)).
- Optimised the element query parameters that are cached. 
- Reordering elements within in a structure now triggers a cache refresh.
- The results of console commands are now logged.
- Improved IE compatibility in inject script.

### Fixed
- Fixed a bug in which URLs containing encoded characters would not be served from cached files ([#222](https://github.com/putyourlightson/craft-blitz/issues/222)).
- Fixed a bug in which query string containing encoded characters could result in a 404 error using the file storage driver ([#224](https://github.com/putyourlightson/craft-blitz/issues/224)).
- Fixed a bug in which updated User elements were not triggering the clearing of the cache. 
- Fixed a bug in which the homepage was not being cached if it had query strings. 

## 3.6.5 - 2020-05-11
### Fixed
- Fixed a bug with the SEOmatic integration that could cause refresh cache jobs to be recursively added to the queue ([#213](https://github.com/putyourlightson/craft-blitz/issues/213), [#217](https://github.com/putyourlightson/craft-blitz/issues/217)).

## 3.6.4 - 2020-04-29
### Fixed
- Fixed a bug with the SEOmatic integration that could cause issues when saving or deleting entry types ([#215](https://github.com/putyourlightson/craft-blitz/issues/215)).

## 3.6.3 - 2020-04-23
### Changed
- Output comments are no longer included for URIs that have a mime type that is not HTML ([#76](https://github.com/putyourlightson/craft-blitz/issues/76)).
- Changed how the SEOmatic integration works so it respects the `clearCacheAutomatically` setting ([#204](https://github.com/putyourlightson/craft-blitz/issues/204)).

### Fixed
- Fixed a bug in which an expired element that did not exist in the primary site would not be refreshed ([#199](https://github.com/putyourlightson/craft-blitz/issues/199)).
- Fixed a bug when trying to set a queue job priority when using a cache drive that does not support it ([#201](https://github.com/putyourlightson/craft-blitz/issues/201)).
- Fixed an error that could be thrown in rare cases when saving an entry with Neo blocks ([#205](https://github.com/putyourlightson/craft-blitz/issues/205)).
- Fixed an exception that could be thrown when using Redis cache if the connection is broken while attempting to get a cached value.

## 3.6.2 - 2020-03-30
### Fixed
- Fixed a potential error if the `customSiteUris` setting was set to a string.

## 3.6.1 - 2020-03-30
### Changed
- Changed the Blitz inject script so it will work with an XHR page navigation ([#179](https://github.com/putyourlightson/craft-blitz/issues/179)).

### Fixed
- Fixed the console command to warm the cache unnecessarily trying to warm uncacheable pages ([#195](https://github.com/putyourlightson/craft-blitz/issues/195)).
- Fixed the console command to warm the cache not warming custom site URIs ([#196](https://github.com/putyourlightson/craft-blitz/issues/196)).

## 3.6.0 - 2020-03-27
### Added 
- Added `refreshCacheWhenElementSavedUnchanged` and `refreshCacheWhenElementSavedNotLive` config settings.
- Added `beforeAddElement` and `afterAddElement` events to `RefreshCacheService`.

### Changed
- The cache will no longer be refreshed when an element is saved but unchanged (in Craft 3.4.0 or above), configurable using the new `refreshCacheWhenElementSavedUnchanged` config setting.
- The cache will no longer be refreshed  when an element is saved but not live, configurable using the new `refreshCacheWhenElementSavedNotLive` config setting.
- The `craft.blitz.getTemplate` tag now renders the template using the same site as the request originated from.

### Fixed
- Fixed an error that could occur if a site defined in the Cloudflare Zone ID settings was deleted ([#192](https://github.com/putyourlightson/craft-blitz/issues/192)).
- Fixed a bug that could prevent the homepage from being refreshed in some cases.
- Fixed the table alignment in Blitz Cache Utility when the Yii Cache Storage was used.

## 3.5.2 - 2020-03-25
> {warning} This update contains an important bug fix when using Blitz with multi-site. Once updated, the included/excluded URI patterns should be checked that they apply to the correct sites.

### Fixed
- Fixed a bug in which the included/excluded URI patterns could be applied to the wrong sites if their IDs were out of original order.

## 3.5.1 - 2020-03-24
### Changed
- The cached response is now output when the `Application::EVENT_INIT` event is triggered so that other plugins can perform actions before Blitz ends the script ([#189](https://github.com/putyourlightson/craft-blitz/issues/189)).

### Fixed
- Fixed a bug in which pages were being cached when a path param was provided in the query string.

## 3.5.0 - 2020-03-20
### Added
- Added `excludedQueryStringParams` config setting ([#177](https://github.com/putyourlightson/craft-blitz/issues/177)).
- Added `injectScriptEvent` config setting ([#179](https://github.com/putyourlightson/craft-blitz/issues/179)).
- Added Commerce Order as a default non-cacheable element type ([#180](https://github.com/putyourlightson/craft-blitz/issues/180)).

### Changed
- Changed `getTemplate` tag to render a template as if it was a page.
- Changed Blitz inject script to use `CustomEvent` instead of the `Event` constructor ([#183](https://github.com/putyourlightson/craft-blitz/issues/183)).
- Improved UI of tables in settings.

## 3.4.1 - 2020-02-20
### Changed
- Improved File Storage utility UI.

### Fixed
- Fixed incorrect cached page count in File Storage utility when the `Create Gzip Files` setting is enabled.

## 3.4.0 - 2020-02-20
### Added
- Added refreshing of [source tags](https://putyourlightson.com/plugins/blitz#source-tags) to the refresh cache job.

## 3.3.0 - 2020-02-18
### Added
- Added a `Create Gzip File` setting to the File Storage driver (credit to [solvr.no](https://solvr.no)).  
- Added `experimental` label to Local Warmer.

### Changed
- Improved foreign key constraint checks before writing to the database ([#174](https://github.com/putyourlightson/craft-blitz/issues/174)).
- Removed triggering of Blitz's garbage collection from Craft's garbage collection procedure.

## 3.2.0 - 2020-02-04
### Added
- Added the automatic warming of new and updated elements that have not previously been cached ([#141](https://github.com/putyourlightson/craft-blitz/issues/141)).

### Changed
- If the URI has an extension with a matching mime type then it is used in the response ([#167](https://github.com/putyourlightson/craft-blitz/issues/167)).

### Fixed
- Fixed an error that could occur if `limit`  or `offset` was set to a string rather than an integer.
- Fixed call to undefined function `array_key_first` error ([#168](https://github.com/putyourlightson/craft-blitz/issues/168)).

## 3.1.1 - 2020-01-20
### Added
- Added an autosuggest environment variable `Email` field to the Cloudflare Purger.
- Added `BaseIntegration` class.

### Changed
- Element queries that are ordered by random are no longer stored in the database.

## 3.1.0 - 2020-01-07
> {tip} This update optimises the performance of refresh cache jobs. The cache should therefore be manually refreshed following the update.

### Added
- Added element query source IDs for more performant refresh cache jobs.
- Added a `X-Blitz-Warmer` header to cache warming requests.
- Added `beforeSaveCache` and `afterSaveCache` events.

### Changed
- Element query ID parameters are now normalized for more performant refresh cache jobs.
- Improved debug messages.

### Fixed
- Fixed a debug message error that could affect the local cache warmer.

## 3.0.3 - 2019-12-30
### Added
- Added `beforeBlitzInject`, `afterBlitzInject`, `beforeBlitzInjectAll`, `afterBlitzInjectAll` events to inject script.
- Added more debug logs for cases in which pages are not cached.

## 3.0.2 - 2019-12-18
### Added
- Added a `commands` config setting to define the paths to executable shell commands.
- Added config setting warning messages for driver settings in the control panel.
- Added better support for custom remote deployers. 

### Changed
- The `cachePurgerTypes` and `deployerTypes` config settings now define the classes that will be added to the plugin’s default respective types.

## 3.0.1 - 2019-12-10
### Changed
- Removed the addition of the `X-Powered-By` header for `Craft CMS`.

### Fixed
- Fixed an error that could occur when updating the plugin without having previously ever saved the settings.

## 3.0.0 - 2019-12-10
> {warning} The `clearCacheAutomaticallyForGlobals` and `warmCacheAutomaticallyForGlobals` config settings have been replaced by a single new `refreshCacheAutomaticallyForGlobals` config setting. The URI patterns config setting format has changed, see the new format [here](https://github.com/putyourlightson/craft-blitz/blob/3.1.0/src/config.php#L28-L46). The folder path config setting format has changed, see the new format [here](https://github.com/putyourlightson/craft-blitz/blob/3.1.0/src/config.php#L51-L52). 

### Added
- Added replaceable remote deployers (Git Deployer).
- Added replaceable cache warmers (Guzzle Warmer, Local Warmer) with the ability to specify custom site URIs to be warmed.
- Added remote deploy and refresh cached URI actions to utility, console commands and user group permissions.
- Added a table to the database in which drivers can store data.
- Added an optional `params` parameter to the `craft.blitz.getUri` and `craft.blitz.getTemplate` variables.
- Added logging of utility actions and other events and errors to `storage/logs/blitz.log`.
- Added a `debug` config setting to debug why pages are not being cached.
- Added a `refreshCacheAutomaticallyForGlobals` config setting.
- Added a `mutexTimeout` config setting.
- Added `beforeGetResponse`, `afterGetResponse`, `beforeClearCache`, `afterClearCache`, `beforeClearAllCache`, `afterClearAllCache`, `beforeFlushCache`, `afterFlushCache`, `beforeFlushAllCache`, `afterFlushAllCache`, `beforeRefreshCache`, `afterRefreshCache`, `beforeRefreshAllCache`, `afterRefreshAllCache`, `beforeWarmCache`, `afterWarmCache`, `beforeWarmAllCache`, `afterWarmAllCache`, `beforePurgeCache`, `afterPurgeCache`, `beforePurgeAllCache`, `afterPurgeAllCache`,`beforeDeploy`, `afterDeploy`, `beforeDeployAll`, `afterDeployAll` events. 

### Changed
- Changed the URI patterns config setting format, see the new format [here](https://github.com/putyourlightson/craft-blitz/blob/3.1.0/src/config.php#L28-L38). 
- Changed the File Storage folder path config setting format, see the new format [here](https://github.com/putyourlightson/craft-blitz/blob/3.1.0/src/config.php#L51-L52). 
- Replaced the `clearCacheAutomaticallyForGlobals` and `warmCacheAutomaticallyForGlobals` config settings with a `refreshCacheAutomaticallyForGlobals` config setting.
- Replaced the `warmCacheJobPriority` with a `deployJobPriority` config setting.
- Replaced the `WarmCacheService` service with a `BaseCacheWarmer` driver.
- Removed the `beforeOutput` event (use `beforeGetResponse` or `afterGetResponse` instead).
- Changed the `cacheControlHeader` to include `max-age`.
- Made the Mutex locks broader to help catch integrity constraint violation errors.
- General performance and control panel UI improvements.

### Fixed
- Fixed user group permissions for refreshing the cache.

## 2.3.5 - 2019-11-13
### Fixed
- Fixed `cacheDuration` config setting not being applied when generating a cache record ([#141](https://github.com/putyourlightson/craft-blitz/issues/141)).
- Fixed a potential JavaScript error if the `blitz-inject` element is not found ([#139](https://github.com/putyourlightson/craft-blitz/issues/139)).

## 2.3.4 - 2019-10-21
### Changed
- Improved element query caching to help catch exceptions ([#136](https://github.com/putyourlightson/craft-blitz/issues/136)).

## 2.3.3 - 2019-10-11
> {tip} This update optimises the cache invalidation process therefore the cache should be refreshed following the update to take advantage of the improvements.

### Changed
- Improved performance and stability of cache invalidation by removing redundant element queries. 

## 2.3.2 - 2019-10-10
### Fixed
- Fixed bug introduced in 2.3.1.

## 2.3.1 - 2019-10-10
### Added
- Added a timestamp to the `Served by Blitz` HTML comment.

### Fixed
- Fixed element queries not being stored for cache invalidation if they specified an ID to exclude using the `id` parameter.
- Fixed response headers not being sent in the initial cache output in some cases.

## 2.3.0 - 2019-10-05
### Added
- Added the ability to dynamically get and inject templates with the `craft.blitz.getTemplate` template tag and the `TemplatesController::actionGet` controller action ([#129](https://github.com/putyourlightson/craft-blitz/issues/129)).
- Added unique zone IDs for each site to the Cloudflare reverse proxy purger settings.
- Added warm cache delay setting to the Cloudflare reverse proxy purger settings.
- Added the `refreshCacheJobPriority` and `warmCacheJobPriority` config settings to change the priority of the respective cache jobs.
- Added unit tests to the plugin.

### Changed
- Cached pages that Blitz knows about are now cleared earlier in the refresh cache job process.

### Fixed
- Fixed warming of cache URLs that should be excluded from caching ([#130](https://github.com/putyourlightson/craft-blitz/issues/130)).
- Fixed links to the documentation in the plugin settings.

## 2.2.3 - 2019-09-15
### Changed
- Improved tracking of element queries in the database.

## 2.2.2 - 2019-09-15
### Fixed
- Fixed bug introduced in 2.2.0.

## 2.2.1 - 2019-09-15
### Changed
- Improved the performance of warming the entire site by excluding irrelevant URLs.

## 2.2.0 - 2019-09-14
### Added
- Added `X-Requested-With: XMLHttpRequest` header to dynamic content AJAX requests.
- Added a `$cacheComponent` setting to `YiiCacheStorage` ([#128](https://github.com/putyourlightson/craft-blitz/issues/128)).

### Changed
- Pages are no longer cached if there are any transform generation URLs in the body ([#125](https://github.com/putyourlightson/craft-blitz/issues/125)).
- Improved tracking of element queries in the database.

### Fixed
- Fixed the warning about the `cachingEnabled` config setting that was not appearing.

## 2.1.5 - 2019-07-26
### Added
- Added the ability to dynamically fetch the CSRF param and a CSRF token with the `craft.blitz.csrfParam` and `craft.blitz.csrfToken` template tags and the `CsrfController::actionParam` and `CsrfController::actionToken` controller actions.
- Added the `blitz/cache/generate-expiry-dates` console command that generates expiry dates for entries and stores them to enable refreshing expired cache (this generally happens automatically).

### Changed
- Changed how `X-Powered-By` headers are returned.

### Fixed
- Fixed an issue in which the cache could be warmed even when caching was disabled.

## 2.1.4 - 2019-07-16
### Changed
- Changed minimum requirement of Craft to version 3.2.1 for detecting preview requests.
- Improved SEOmatic integration when resaving elements.

### Fixed
- Fixed an issue in which Block element types were being treated as elements.
- Fixed a migration that could throw an error when updating with `allowAdminChanges` disabled ([#114](https://github.com/putyourlightson/craft-blitz/issues/114)).
- Fixed an error that was being thrown when running the resave entries console command ([#115](https://github.com/putyourlightson/craft-blitz/issues/115)).

## 2.1.3 - 2019-07-12
### Fixed
- Fixed commented code in SEOmatic integration. 😈  

## 2.1.2 - 2019-07-12
### Fixed
- Fixed minimum required SEOmatic version number.  

## 2.1.1 - 2019-07-12
### Changed
- Changed SEOmatic integration to require version 3.2.14. 

### Fixed
- Fixed MySQL database error that could occur with `innodb_large_prefix` disabled when installing or updating the plugin ([#113](https://github.com/putyourlightson/craft-blitz/issues/113)).
- Fixed plugin integrations to only be loaded if the required plugin version is met.

## 2.1.0 - 2019-07-10
> {note} This update requires some changes to the server rewrite code in order to continue working with live preview. Please reference the [documentation](https://putyourlightson.com/plugins/blitz#server-rewrites) for details.

### Added
- Added compatibility for drafts and revisions in Craft 3.2.0.
- Added integrations with Feed Me and SEOmatic ([#93](https://github.com/putyourlightson/craft-blitz/issues/93)).
- Added queue job progress labels when refreshing and warming the cache.
- Added Campaign plugin contacts as non cacheable element type.
- Added new events to detect when elements are being resaved and propagated in order to prevent multiple refresh cache jobs from being unnecessarily created ([#98](https://github.com/putyourlightson/craft-blitz/issues/98)).
- Added user group permissions for each of the available functions in the Blitz cache utility ([#104](https://github.com/putyourlightson/craft-blitz/issues/104)).
- Added `beforeOutput` event ([#112](https://github.com/putyourlightson/craft-blitz/issues/112)).

### Changed
- Changed minimum requirement of Craft to version 3.2.0.
- Improved queue job progress feedback when refreshing and warming the cache.
- Assets that are being indexed no longer trigger refresh cache jobs. 
- Settings are now saved with a warning message even if purger test fails.
- Changed maximum length of URIs to 500 characters([#105](https://github.com/putyourlightson/craft-blitz/issues/105)).

## 2.0.10 - 2019-07-26
### Added
- Added the `blitz/cache/generate-expiry-dates` console command that generates entry expiry dates and stores them to enable refreshing expired cache (this generally happens automatically)

## 2.0.9 - 2019-07-16
> {note} This update adds better handling of drafts and revisions and is recommended to install before updating to Craft 3.2.

### Added
- Added better handling of drafts and revisions for the update process to Craft 3.2.
- Added Super Table elements and Campaign plugin contacts as non cacheable element types.

## 2.0.8 - 2019-04-05
### Added
- Added `clearCacheAutomaticallyForGlobals` config setting.

### Changed
- Removed prepending of BOM characters to cached values to force UTF8 encoding ([#89](https://github.com/putyourlightson/craft-blitz/issues/89)).
- Changed default `cachePurgerSettings` to reflect Cloudflare purger in `config.php`.

## 2.0.7 - 2019-03-26
### Changed
- Implemented extra preventative measures to help avoid integrity constraint violations when writing to the database.

## 2.0.6 - 2019-03-25
### Changed
- Optimised how element site URIs are fetched from database.

### Fixed
- Fixed check for cacheable request when system is turned off.

## 2.0.5 - 2019-03-18
### Added
- Added CP template roots for custom cache purgers.

## 2.0.4 - 2019-03-18
### Changed
- Changed use of database transaction with mutex when generating and saving cache in database.

### Fixed
- Fixed redirect after install to only be called on web requests.
- Fixed error that could occur when migrating from Blitz version 1.  

## 2.0.3 - 2019-03-15
### Changed
- Reverted changes to migrations to only run if schema version in `project.yaml` has not already been updated (red herring).

## 2.0.2 - 2019-03-15
### Added
- Added a warning in the settings if the `@web` alias is used in a site’s base URL.

### Changed
- Changed migrations only run if schema version in `project.yaml` has not already been updated. 
- Changed how database rows are inserted in bulk to prevent integrity constraint violation errors.

## 2.0.1 - 2019-03-14
### Added
- Added `refreshAll()` method to `RefreshCacheService`.

### Fixed
- Fixed site base URL when it contains an environment variable ([#80](https://github.com/putyourlightson/craft-blitz/issues/80)).  

## 2.0.0 - 2019-03-14
### Added
- Added replaceable cache drivers (File Storage, Yii Cache).
- Added replaceable reverse proxy purgers (Cloudflare Purger).
- Added twig tag for setting template specific cache options including tags and expiry dates.
- Added refresh tagged cache action to utility and controllers.
- Added purge cache action to utility and controllers.
- Added environment variables to file driver field.
- Added garbage collection to cache records and element query records.
- Added welcome screen that appears after install.
- Added logging of file and request exceptions.
- Added prevention of caching dynamically injected content.
- Added an API key that can be used to clear, flush, purge, warm and refresh cache via a URL. 
- Added cache tag header when reverse proxy purger is enabled.
- Added warning in console `cache/warm` command if `@web` is unparsed in a site base URL.
- Added `clearCacheAutomatically` setting.
- Added `cacheControlHeader` config setting.
- Added `outputComments` config setting.

### Changed
- Changed minimum requirement of Craft to version 3.1.0.
- Changed behaviour of flush and warm cache actions.
- Cache header tags are added on the first render of a cacheable page.
- Cache is invalidated when elements are updated through non control panel requests.
- The primary site is now warmed first.
- Global set updates only clear the cache of the site that they belong to.
- Neo blocks are now considered non-cacheable element types.
- Cached pages are not output to users who cannot access the site when the system is off.
- File storage driver only saves files in sub paths of the site path. 

## 1.11.5 - 2019-02-14
### Fixed
- Fixed refreshing of expired cache when run through a console command.

## 1.11.4 - 2019-01-23
### Fixed
- Fixed error which could appear when query params were longer than the available column size. 

## 1.11.3 - 2019-01-09
### Added
- Added clear cache options in control panel and console commands (requires Craft 3.0.37 or above).

### Fixed
- Fixed bug whereby the the cache was being flushed too early when warming with the utility.
- Fixed expiry date being deleted too early when an element cache was refreshed if it had both a future post and expiry date.

## 1.11.2 - 2019-01-06
### Changed
- Expiry dates are now applied to all element types that have `postDate` and `expiryDate` fields, not just entries.
- Changed cache folder path to relative path in utilty.

### Fixed
- Fixed functionality for elements with future post dates when refreshing expired cache.

## 1.11.1 - 2019-01-01
### Fixed
- Fixed bug when refreshing expired cache.

## 1.11.0 - 2019-01-01
> {note} This update optimises the plugin's database tables and the cache should therefore be warmed manually following the update.

### Added
- Added expiry date to elements, specifically to entries with future post dates or expiry dates.
- Added utility and console command to refresh elements with expiry dates.
- Added `nonCacheableElementTypes` config setting.
- Added optimisations to make the cached element queries more lightweight.

### Changed
- Replaced ability to clear sites in utility with table of cached sites.

## 1.10.2 - 2018-12-19
### Added
- Added flush cache utility and console command. 
- Added `warmCacheAutomaticallyForGlobals` config setting.

## 1.10.1 - 2018-12-17
### Added
- Added comments to Blitz console commands when running `./craft` from the terminal. 

### Fixed
- Fixed error when clearing the cache from the utility.

## 1.10.0 - 2018-12-17
> {note} This update optimises the plugin's database tables and the cache should therefore be warmed manually following the update.

### Added
- Added optimisations to make the caching process faster and more performant.
- Added `afterRefreshCache` event for third-party plugins to use.
- Added a blank field as an alias for the homepage when matching URI patterns.

### Changed
- Changed ability to clear individual file paths to entire sites in utility.
- Changed dynamically injected tag from `div` to `span` and added a `blitz-inject` class for easier styling.

### Fixed
- Fixed config warnings that were not being displayed on settings page.

## 1.9.2 - 2018-12-10
### Added
- Added `sendPoweredByHeader` config setting to determine whether an `X-Powered-By: Blitz` header should be sent.

### Fixed
- Fixed a bug in which homepage was not cached if the site base URL contained a trailing slash.

## 1.9.1 - 2018-12-06
### Fixed
- Fixed a bug that prevented the CSRF field being shown to anonymous users.

## 1.9.0 - 2018-12-04
> {tip} You can now inject dynamic content into cached pages using the new twig tags.

### Added
- Added a twig tag to dynamically inject the content of a URI into a cached page.
- Added a twig tag to dynamically inject a CSRF input field into a cached page.

## 1.8.0 - 2018-11-28
> {tip} A new `concurrency` setting is available in the plugin settings for faster cache warming.

> {note} This update optimises the plugin's database tables and the cache should therefore be warmed manually following the update.

### Added 
- Added multiple concurrent requests when warming the cache with a new plugin setting.
- Added new option to cache URLs with unique query strings as the same page ([#40](https://github.com/putyourlightson/craft-blitz/issues/40)).
- Added the ability to disable element caches and element query caches from being stored in the database with the config settings `cacheElements` and `cacheElementQueries` ([#41](https://github.com/putyourlightson/craft-blitz/issues/41)).
- Added check for currently logged in user having the `enableDebugToolbarForSite` setting enabled, in which case caching does not happen.
 
### Changed
- Optimised how element queries are stored to avoid duplicates and reduce required database storage ([#41](https://github.com/putyourlightson/craft-blitz/issues/41)).
- Improved messages and progress bar behaviour in warm cache console command.

### Fixed
- Fixed bug where warm cache job fails if a server error is encountered ([#42](https://github.com/putyourlightson/craft-blitz/issues/42)).

## 1.7.1 - 2018-11-20
### Fixed 
- Fixed info text for URI patterns.

## 1.7.0 - 2018-11-20
### Added 
- Added multi-site functionality to URI pattern matching ([#39](https://github.com/putyourlightson/craft-blitz/issues/39)).
- Added progress bar to warm cache console command.

## 1.6.9 - 2018-11-19
### Changed
- Optimised caching process to speed up initial page load time.
- Changed Guzzle client to use default config values.

### Fixed
- Fixed a bug with caching paginated pages.


## 1.6.8 - 2018-11-14
### Fixed
- Fixed a bug that affected sites with locale/language segments in the base URL.

## 1.6.7 - 2018-11-12
### Fixed
- Fixed caching and invalidation of URIs with query strings.

## 1.6.6 - 2018-11-09
### Fixed
- Fixed a bug introduced in 1.6.5.

## 1.6.5 - 2018-11-09
### Fixed
- Fixed a bug when checking whether URLs with query strings should cached.
- Fixed an error that could occur if the URI pattern was set to "`*`".

## 1.6.4 - 2018-11-07
### Changed
- Cached files are deleted immediately when elements that have a URI are updated.

## 1.6.3 - 2018-11-04
### Changed
- Improved performance of cache invalidation in refresh job.

## 1.6.2 - 2018-11-03
### Fixed
- Fixed an error that could occur if no host was found in the site URL.

## 1.6.1 - 2018-11-02
### Fixed
- Fixed a site URL alias that was not converted to an absolute URL.

## 1.6.0 - 2018-11-02
### Added
- Added queue job for refreshing cache based on element changes which can require a lot of processing.
- Added `registerNonCacheableElementTypes` event to `CacheService` class.

### Changed
- Optimised caching tables by not caching global sets or matrix blocks.

## 1.5.5 - 2018-11-01
### Fixed
- Fixed a bug that originated from leading slashes not being trimmed when matching a URI [[#29](https://github.com/putyourlightson/craft-blitz/issues/29)].

## 1.5.4 - 2018-09-28
### Fixed
- Fixed bug when saving elements when caching is enabled.

## 1.5.3 - 2018-09-17
### Fixed
- Fixed bug that could occur when element query cache records had no existing cache ID.

## 1.5.2 - 2018-09-04
### Added
- Added "Warm Cache Automatically" setting ([#21](https://github.com/putyourlightson/craft-blitz/issues/21)).

## 1.5.1 - 2018-08-17
### Changed
- Patterns are normalized to strings to allow for flat arrays in config settings ([#17](https://github.com/putyourlightson/craft-blitz/issues/17#issuecomment-413897648)).

## 1.5.0 - 2018-07-25
### Added
- Added "Query String Caching" setting.
- Added `%{QUERY_STRING}` to `mod_rewrite` code sample in docs.
### Changed
- Disabled caching of URLs beginning with `/index.php` to avoid issues when `mod_rewrite` is enabled.

## 1.4.3 - 2018-07-23
### Fixed
- Fixed bug with App not being defined when cache warmed.

## 1.4.2 - 2018-07-16
### Changed
- Calls for max power before warming the cache to prevent timeouts or memory limits being exceeded.
- Enabled cache clearing even if "Caching Enabled" setting is disabled.

## 1.4.1 - 2018-07-10
### Fixed
- Fixed bug where 404 error templates were being cached.
- Fixed error that occurred when warming cache with the console command when `@web` was used in a site URL.

## 1.4.0 - 2018-07-05
### Added
- Added automatic cache clearing and warming to pages that contain element queries.

## 1.3.0 - 2018-07-03
### Added
- Added functionality for multi-site setups.
- Added UTF8 encoding to cached files .

## 1.2.3.1 - 2018-07-01
### Fixed
- Fixed bug when reordering structure elements in the CP.

## 1.2.3 - 2018-07-01
### Fixed
- Fixed bug when reordering structure elements in the CP.

## 1.2.2 - 2018-07-01
### Changed
- Changed plugin icon.

## 1.2.1 - 2018-06-30
### Fixed
- Fixed bug where excluded URIs were still being cached.

## 1.2.0 - 2018-06-29
### Added
- Added button to utility to clear all cache.
- Added button to utility to warm cache.
- Added console command to warm cache.

## 1.1.1 - 2018-06-29
### Added
- Added console command to clear all cache.

### Fixed
- Fixed error that occurred when running console commands.

## 1.1.0 - 2018-06-28
### Added
- Added cache breaking for all cached template files that used an element that is saved or deleted.

### Changed
- Changed template render event to a later firing event.

## 1.0.4 - 2018-06-28
### Fixed
- Fixed clearing cache when URI is homepage.

## 1.0.3 - 2018-06-28
### Fixed
- Fixed error that could occur when a section has a blank URI format.

## 1.0.2 - 2018-06-27
### Fixed
- Fixed cachingEnabled setting checks.

## 1.0.1 - 2018-06-27
### Fixed
- Fixed error when URI Patterns were empty.

## 1.0.0 - 2018-06-27
- Initial release.
