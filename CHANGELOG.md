# Release Notes for Blitz

## 3.0.0 - Unreleased
### Added
- Added replaceable cache warmers (Local warmer).
- Added replaceable deployers (Git deployer).

### Changed
- Changed `cachePurgerType`, `cachePurgerSettings` and `cachePurgerTypes` config settings to `purgerType`, `purgerSettings` and `purgerTypes` respectively.

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
> {note} This release requires some changes to the server rewrite code in order to continue working with live preview. Please reference the [documentation](https://putyourlightson.com/craft-plugins/blitz/docs#/?id=server-rewrites) for details.

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
> {note} This release adds better handling of drafts and revisions and is recommended to install before updating to Craft 3.2.

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
- Added replaceable cache drivers (File storage, Yii cache).
- Added replaceable reverse proxy purgers (Cloudflare).
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
> {note} This release optimises the plugin's database tables and the cache should therefore be warmed manually following the update.

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
> {note} This release optimises the plugin's database tables and the cache should therefore be warmed manually following the update.

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

> {note} This release optimises the plugin's database tables and the cache should therefore be warmed manually following the update.

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
