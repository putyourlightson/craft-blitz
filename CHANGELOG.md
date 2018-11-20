# Release Notes for Blitz

## 1.7.1 - 2018-11-20
### Fixed 
- Fixed info text for URI patterns

## 1.7.0 - 2018-11-20
### Added 
- Added multi-site functionality to URI pattern matching ([#39](https://github.com/putyourlightson/craft-blitz/issues/39))
- Added progress bar to warm cache console command

## 1.6.9 - 2018-11-19
### Changed
- Optimised caching process to speed up initial page load time
- Changed Guzzle client to use default config values

### Fixed
- Fixed a bug with caching paginated pages


## 1.6.8 - 2018-11-14
### Fixed
- Fixed a bug that affected sites with locale/language segments in the base URL

## 1.6.7 - 2018-11-12
### Fixed
- Fixed caching and invalidation of URIs with query strings

## 1.6.6 - 2018-11-09
### Fixed
- Fixed a bug introduced in 1.6.5

## 1.6.5 - 2018-11-09
### Fixed
- Fixed a bug when checking whether URLs with query strings should cached 
- Fixed an error that could occur if the URI pattern was set to "`*`"

## 1.6.4 - 2018-11-07
### Changed
- Cached files are deleted immediately when elements that have a URI are updated 

## 1.6.3 - 2018-11-04
### Changed
- Improved performance of cache invalidation in refresh job

## 1.6.2 - 2018-11-03
### Fixed
- Fixed an error that could occur if no host was found in the site URL

## 1.6.1 - 2018-11-02
### Fixed
- Fixed a site URL alias that was not converted to an absolute URL

## 1.6.0 - 2018-11-02
### Added
- Added queue job for refreshing cache based on element changes which can require a lot of processing
- Added `registerNonCacheableElementTypes` event to `CacheService` class

### Changed
- Optimised caching tables by not caching global sets or matrix blocks

## 1.5.5 - 2018-11-01
### Fixed
- Fixed a bug that originated from leading slashes not being trimmed when matching a URI [[#29](https://github.com/putyourlightson/craft-blitz/issues/29)]

## 1.5.4 - 2018-09-28
### Fixed
- Fixed bug when saving elements when caching is enabled

## 1.5.3 - 2018-09-17
### Fixed
- Fixed bug that could occur when element query cache records had no existing cache ID

## 1.5.2 - 2018-09-04
### Added
- Added "Warm Cache Automatically" setting ([#21](https://github.com/putyourlightson/craft-blitz/issues/21))

## 1.5.1 - 2018-08-17
### Changed
- Patterns are normalized to strings to allow for flat arrays in config settings ([#17](https://github.com/putyourlightson/craft-blitz/issues/17#issuecomment-413897648))

## 1.5.0 - 2018-07-25
### Added
- Added "Query String Caching" setting
- Added `%{QUERY_STRING}` to `mod_rewrite` code sample in docs
### Changed
- Disabled caching of URLs beginning with `/index.php` to avoid issues when `mod_rewrite` is enabled

## 1.4.3 - 2018-07-23
### Fixed
- Fixed bug with App not being defined when cache warmed

## 1.4.2 - 2018-07-16
### Changed
- Calls for max power before warming the cache to prevent timeouts or memory limits being exceeded
- Enabled cache clearing even if "Caching Enabled" setting is disabled

## 1.4.1 - 2018-07-10
### Fixed
- Fixed bug where 404 error templates were being cached
- Fixed error that occurred when warming cache with the console command when `@web` was used in a site URL 

## 1.4.0 - 2018-07-05
### Added
- Added automatic cache clearing and warming to pages that contain element queries 

## 1.3.0 - 2018-07-03
### Added
- Added functionality for multi-site setups
- Added UTF8 encoding to cached files  

## 1.2.3.1 - 2018-07-01
### Fixed
- Fixed bug when reordering structure elements in the CP

## 1.2.3 - 2018-07-01
### Fixed
- Fixed bug when reordering structure elements in the CP

## 1.2.2 - 2018-07-01
### Changed
- Changed plugin icon

## 1.2.1 - 2018-06-30
### Fixed
- Fixed bug where excluded URIs were still being cached

## 1.2.0 - 2018-06-29
### Added
- Added button to utility to clear all cache
- Added button to utility to warm cache
- Added console command to warm cache

## 1.1.1 - 2018-06-29
### Added
- Added console command to clear all cache

### Fixed
- Fixed error that occurred when running console commands

## 1.1.0 - 2018-06-28
### Added
- Added cache breaking for all cached template files that used an element that is saved or deleted

### Changed
- Changed template render event to a later firing event

## 1.0.4 - 2018-06-28
### Fixed
- Fixed clearing cache when URI is homepage

## 1.0.3 - 2018-06-28
### Fixed
- Fixed error that could occur when a section has a blank URI format

## 1.0.2 - 2018-06-27
### Fixed
- Fixed cachingEnabled setting checks

## 1.0.1 - 2018-06-27
### Fixed
- Fixed error when URI Patterns were empty

## 1.0.0 - 2018-06-27
- Initial release
