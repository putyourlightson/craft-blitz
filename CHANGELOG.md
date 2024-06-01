# Release Notes for Blitz

## 5.3.2 - Unreleased

### Fixed

- Fixed a bug in which modules that were not bootstrapped were throwing an error when generating a report in the Blitz Diagnostics utility ([#668](https://github.com/putyourlightson/craft-blitz/issues/668)).

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

The `blitz/cache/refresh-cache-tags` and `blitz/cache/refresh-expired-elements` no longer forcibly generate the cache.

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
