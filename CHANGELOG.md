# Release Notes for Blitz

## 4.3.0 - Unreleased
### Added
- Added a cached includes column to the Blitz cache utility for the File Cache Storage driver. 
- Added the `ssiEnabled` config setting that enables Blitz to include templates using Server Side Includes (SSI), which must be enabled on the web server.
- Added the `esiEnabled` config setting that enables Blitz to include templates using Edge Side Includes (ESI), which must be enabled on the web server or reverse proxy (CDN).
- Added the `craft.blitz.include()` template variable, that includes a template using SSI if enabled, otherwise via an AJAX request. The `include()` method returns a cached result (if one exists).
- Added the `craft.blitz.dynamicInclude()` template variable, that includes a template via an AJAX request. The `dynamicInclude()` method always returns a freshly rendered template.
- Added the `craft.blitz.fetch()` template variable, that fetches a URI via an AJAX request. Whether the URI response is cached or not is determined by the URI patterns in the plugin settings.
- Added the `blitz/templates/include` and `blitz/templates/dynamic-include` controller actions.

### Changed
- Improved the detection of when elements should be refreshed based on changes.
- Improved the performance of refresh job requests when cache generation is disabled ([#456](https://github.com/putyourlightson/craft-blitz/issues/456)). 
- Generator, deployer and purger jobs are now released before refreshing the entire cache, provided cache clearing is enabled ([#454](https://github.com/putyourlightson/craft-blitz/issues/454)). 

### Deprecated
- Deprecated the `craft.blitz.getTemplate()` template variable. Use `craft.blitz.include()` or `craft.blitz.dynamicInclude()` instead.
- Deprecated the `craft.blitz.getUri()` template variable. Use `craft.blitz.fetch()` instead.
- Deprecated the `blitz/templates/get` controller action. Use `blitz/templates/include` or `blitz/templates/dynamic-include` instead.

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
