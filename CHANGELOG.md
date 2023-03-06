# Release Notes for Blitz

## 4.5.0 - Unreleased
### Added
- Added the ability to send compressed responses to browsers that accept supported encodings.
- Added the ability to save compressed cached values in the Yii Cache Storage to help reduce the memory required.
- Added tips that display whether `gzip` is enabled on the web server in the Blitz File Storage settings.

### Changed
- Cached includes and pages that contain SSI or ESI includes are now never compressed.
- Renamed the `createGzipFiles` setting to `compressCachedValues`.
- Removed the ability to create Brotli files and removed the `createBrotliFiles` setting.

### Fixed
- Fixed the `generatePageBasedOnQueryString` check and ensured that cached includes are always allowed.

### Deprecated
- Deprecated the `createGzipFiles` setting.
- Deprecated the `createBrotliFiles` setting.

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
