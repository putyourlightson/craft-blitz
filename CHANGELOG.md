# Release Notes for Blitz

## 5.6.3 - 2024-08-15

> [!NOTE]
> The cache should be cleared or refreshed after this update completes.

### Changed

- Recreated some database tables to ensure that composite primary keys are correctly created.

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
- Added a
  `dateCached` column to cache records which is output in the sidebar panel and the Blitz Diagnostics utility.
- Added the ability to track eager-loaded relation fields nested inside matrix blocks ([#657](https://github.com/putyourlightson/craft-blitz/issues/657)).
- Added a structure view to tracked nested element pages in the Blitz Diagnostics utility.

### Changed

- The `craft.blitz.csrfInput()`, `craft.blitz.csrfParam()` and
  `craft.blitz.csrfToken()` functions now output inline values rather than inject scripts when called via AJAX requests.
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

- Fixed a bug in which the priority of refresh cache and driver jobs was interpreted as
  `0` when set to
  `null` ([#655](https://github.com/putyourlightson/craft-blitz/issues/655)).
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

- Fixed the check for whether the
  `blitz/cache/refresh-expired` console command was executed within the past 24 hours.
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
- Added suggesting the use of the
  `eagerly()` function to lazy-loaded element queries in the Blitz Hints utility.

### Changed

- The Blitz Hints utility is now powered by Sprig, no longer tracks route variable hints and no longer requires an external package.

### Removed

- Removed the `craft.blitz.getTemplate()` template variable. Use
  `craft.blitz.includeCached()` or `craft.blitz.includeDynamic()` instead.
- Removed the `craft.blitz.getUri()` template variable. Use
  `craft.blitz.fetchUri()` instead.
- Removed the `blitz/templates/get` controller action.
- Removed the `cacheElements` config setting. Use `trackElements` instead.
- Removed the `cacheElementQueries` config setting. Use
  `trackElementQueries` instead.
- Removed the `craft.blitz.options.cacheElements()` template variable. Use
  `craft.blitz.options.trackElements()` instead.
- Removed the `craft.blitz.options.cacheElementQueries()` template variable. Use
  `craft.blitz.options.trackElementQueries()` instead.
- Removed the `createGzipFiles` setting.
- Removed the `createBrotliFiles` setting.
- Removed the `BlitzVariable::CACHED_INCLUDE_ACTION` constant. Use
  `CacheRequestService::CACHED_INCLUDE_ACTION` instead.
- Removed the `BlitzVariable::DYNAMIC_INCLUDE_ACTION` constant. Use
  `CacheRequestService::DYNAMIC_INCLUDE_ACTION` instead.
- Removed the `ElementTypeHelper::LIVE_STATUSES` constant.
- Removed the `SettingsModel::clearOnRefresh` method. Use
  `SettingsModel::shouldClearOnRefresh` instead.
- Removed the `SettingsModel::expireOnRefresh` method. Use
  `SettingsModel::shouldExpireOnRefresh` instead.
- Removed the `SettingsModel::generateOnRefresh` method. Use
  `SettingsModel::shouldGenerateOnRefresh` instead.
- Removed the `SettingsModel::purgeAfterRefresh` method. Use
  `SettingsModel::shouldPurgeAfterRefresh` instead.
- Removed the `SettingsModel::generatePageBasedOnQueryString` method. Use
  `SettingsModel::shouldGeneratePageBasedOnQueryString` instead.
- Removed the `SettingsModel::purgeAssetImages` method. Use
  `SettingsModel::shouldPurgeAssetImages` instead.
