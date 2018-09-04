# Blitz Changelog

## 1.5.2 - 2018-09-04
### Added
- Added "Warm Cache Automatically" setting

## 1.5.1 - 2018-08-17
### Changed
- Patterns are normalized to strings to allow for flat arrays in config settings ([github issue](https://github.com/putyourlightson/craft-blitz/issues/17#issuecomment-413897648))

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
