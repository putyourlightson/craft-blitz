# Release Notes for Blitz

## 5.0.0-beta.3 - 2024-03-21

### Fixed

- Fixed the Blitz Hints logic for detecting lazy-loaded element queries.

## 5.0.0-beta.2 - 2024-03-20

### Added

- Added suggesting the use of the `eagerly()` function to lazy-loaded element queries in the Blitz Hints utility.
- Added a template stack trace to the Blitz Hints utility.
- Added batched generate cache jobs ([#537](https://github.com/putyourlightson/craft-blitz/issues/537)).
- Added a new `driverJobBatchSize` config setting that sets the batch size to use for driver jobs that support batching.
- Added a new `refreshCacheEnabled` config setting that determines whether cached pages are refreshed whenever content changes or an integration triggers it.
- Added a new `injectScriptPosition` config setting that determines the position in the HTML in which to output the injected script ([#636](https://github.com/putyourlightson/craft-blitz/issues/636)).
- Added a verbose output mode to `blitz/cache` console commands that can be activated by adding a `--verbose` flag ([#642](https://github.com/putyourlightson/craft-blitz/issues/642)).
- Added a default timeout of 60 seconds to the Local Generator.

### Changed

- The Blitz Hints utility is now powered by Sprig, no longer tracks route variable hints and no longer requires an external package.
- Elements that are propagating are no longer ignored from the cache refresh process ([#631](https://github.com/putyourlightson/craft-blitz/issues/631)).
- Changed the default branch in the Git Deployer to `main`.
- The Local Generator now uses the `bootstrap.php` file in the project root, if it exists.
- The Local Generator now sets the server port according to the HTTP protocol.
- Changed the default timeout of the HTTP Generator to 60 seconds.

### Fixed

- Fixed an SQL error that could occur when too many site URIs were being expired at once during the refresh cache process ([#639](https://github.com/putyourlightson/craft-blitz/issues/639)).
- Fixed minor bugs and typos in the recommendations provided in the Blitz Diagnostics utility ([#641](https://github.com/putyourlightson/craft-blitz/issues/641)).

### Removed

- Removed the `SettingsModel::clearOnRefresh` method. Use `SettingsModel::shouldClearOnRefresh` instead.
- Removed the `SettingsModel::expireOnRefresh` method. Use `SettingsModel::shouldExpireOnRefresh` instead.
- Removed the `SettingsModel::generateOnRefresh` method. Use `SettingsModel::shouldGenerateOnRefresh` instead.
- Removed the `SettingsModel::purgeAfterRefresh` method. Use `SettingsModel::shouldPurgeAfterRefresh` instead.
- Removed the `SettingsModel::generatePageBasedOnQueryString` method. Use `SettingsModel::shouldGeneratePageBasedOnQueryString` instead.
- Removed the `SettingsModel::purgeAssetImages` method. Use `SettingsModel::shouldPurgeAssetImages` instead.

## 5.0.0-beta.1 - 2024-02-14

### Added

- Added compatibility with Craft 5.0.0.

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
