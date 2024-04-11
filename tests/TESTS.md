# Test Specification

This document outlines the test specification for the Blitz plugin.

---

## Feature Tests

### [CacheStorage](pest/Feature/CacheStorageTest.php)

_Tests the storing of cached values using the cache storage drivers._

![Pass](https://raw.githubusercontent.com/putyourlightson/craft-generate-test-spec/main/icons/pass.svg) Long site URI can be saved except for by file storage driver with data set `FileStorage`.  
![Pass](https://raw.githubusercontent.com/putyourlightson/craft-generate-test-spec/main/icons/pass.svg) Long site URI can be saved except for by file storage driver with data set `YiiCacheStorage`.  
![Pass](https://raw.githubusercontent.com/putyourlightson/craft-generate-test-spec/main/icons/pass.svg) Long site URI can be saved except for by file storage driver with data set `RedisStorage`.  
