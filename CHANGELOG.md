# Release Notes for Blitz

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
