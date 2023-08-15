# Test Specification

This document outlines the test specification for the Blitz plugin.

---

## Feature Tests

### [CacheRequest](pest/Feature/CacheRequestTest.php)

> _Tests whether requests are cacheable and under what circumstances._

:green_circle: Request matching included URI pattern is cacheable.  
:green_circle: Request with generate token is cacheable.  
:green_circle: Request with `no-cache` param is not cacheable.  
:green_circle: Request with token is not cacheable.  
:green_circle: Request with `_includes` path is a cached include.  
:green_circle: Request with include action is a cached include.  
:green_circle: Requested cacheable site URI includes allowed query strings when urls cached as unique pages.  
:green_circle: Requested cacheable site URI does not include query strings when urls cached as same page.  
:green_circle: Requested cacheable site URI includes page trigger.  
:green_circle: Requested cacheable site URI works with regular expressions.  
:green_circle: Site URI with included URI pattern is cacheable.  
:green_circle: Site URI with excluded URI pattern is not cacheable.  
:green_circle: Site URI with `admin` in URI is cacheable.  
:green_circle: Site URI with `index.php` in URI is not cacheable.  
:green_circle: Site URI with max URI length is cacheable.  
:green_circle: Site URI with max URI length exceeded is not cacheable.  
:green_circle: URI patterns with matching regular expressions are matched.  
:green_circle: URI patterns without matching regular expressions are not matched.  

### [CacheStorage](pest/Feature/CacheStorageTest.php)

> _Tests the storing of cached values using the cache storage drivers._

:green_circle: 255 character site URI can be saved with data set "FileStorage".  
:green_circle: 255 character site URI can be saved with data set "YiiCacheStorage".  
:green_circle: 255 character site URI can be saved with data set "RedisStorage".  
:green_circle: Long site URI can be saved except for by file storage driver with data set "FileStorage".  
:green_circle: Long site URI can be saved except for by file storage driver with data set "YiiCacheStorage".  
:green_circle: Long site URI can be saved except for by file storage driver with data set "RedisStorage".  
:green_circle: Site URI is decoded before being saved with data set "FileStorage".  
:green_circle: Site URI is decoded before being saved with data set "YiiCacheStorage".  
:green_circle: Site URI is decoded before being saved with data set "RedisStorage".  
:green_circle: Compressed cached value can be fetched compressed and uncompressed with data set "FileStorage".  
:green_circle: Compressed cached value can be fetched compressed and uncompressed with data set "YiiCacheStorage".  
:green_circle: Compressed cached value can be fetched compressed and uncompressed with data set "RedisStorage".  
:green_circle: Cached value of site URI can be deleted with data set "FileStorage".  
:green_circle: Cached value of site URI can be deleted with data set "YiiCacheStorage".  
:green_circle: Cached value of site URI can be deleted with data set "RedisStorage".  
:green_circle: Cached value of decoded site URI can be deleted with data set "FileStorage".  
:green_circle: Cached value of decoded site URI can be deleted with data set "YiiCacheStorage".  
:green_circle: Cached value of decoded site URI can be deleted with data set "RedisStorage".  
:green_circle: All cached values can be deleted with data set "FileStorage".  
:green_circle: All cached values can be deleted with data set "YiiCacheStorage".  
:green_circle: All cached values can be deleted with data set "RedisStorage".  

### [GenerateCache](pest/Feature/GenerateCacheTest.php)

> _Tests the saving of cached values, element cache records and element query records._

:green_circle: Cached value is saved with output comments.  
:green_circle: Cached value is saved without output comments.  
:green_circle: Cached value is saved with output comments when file extension is `.html`.  
:green_circle: Cached value is saved without output comments when file extension is not `.html`.  
:green_circle: Cache record with max URI length is saved.  
:green_circle: Cache record with max URI length exceeded throws exception.  
:green_circle: Element cache record is saved without custom fields.  
:green_circle: Element cache record is saved with custom fields.  
:green_circle: Element cache record is saved with eager loaded custom fields.  
:green_circle: Element cache record is saved with eager loaded custom fields in variable.  
:green_circle: Element query records without specific identifiers are saved.  
:green_circle: Element query records with specific identifiers are not saved.  
:green_circle: Element query record with join is saved.  
:green_circle: Element query record with relation field is not saved.  
:green_circle: Element query record with related to param is saved.  
:green_circle: Element query record with expression is not saved.  
:green_circle: Element query cache records are saved.  
:green_circle: Element query source records with specific source identifiers are saved.  
:green_circle: Element query source records without specific source identifiers are not saved.  
:green_circle: Element query attribute records are saved.  
:green_circle: Element query attribute records are saved with order by.  
:green_circle: Element query attribute records are saved with order by parts array.  
:green_circle: Element query attribute records are saved with before.  
:green_circle: Element query field records are saved with order by.  
:green_circle: Element query field records are saved with order by array.  
:green_circle: Cache tags are saved.  
:green_circle: Include record is saved.  
:green_circle: SSI include cache record is saved.  

### [RefreshCache](pest/Feature/RefreshCacheTest.php)

> _Tests the tracking of changes to elements and the resulting element cache IDs and element query type records._

:green_circle: Element is not tracked when it is unchanged.  
:green_circle: Element is tracked when `refreshCacheWhenElementSavedUnchanged` is `true` and it is unchanged.  
:green_circle: Element is not tracked when disabled and its attribute is changed.  
:green_circle: Element is tracked when disabled and `refreshCacheWhenElementSavedNotLive` is `true` and its attribute is changed.  
:green_circle: Element is tracked when its status is changed.  
:green_circle: Element is tracked when it expires.  
:green_circle: Element is tracked when it is deleted.  
:green_circle: Element is tracked when its attribute is changed.  
:green_circle: Element is tracked when its field is changed.  
:green_circle: Element is tracked when its attribute and field are changed.  
:green_circle: Element is tracked when its status and attribute and field are changed.  
:green_circle: Asset is tracked when its file is replaced.  
:green_circle: Asset is tracked when its filename is changed.  
:green_circle: Asset is tracked when its focal point is changed.  
:green_circle: Element expiry date record is saved when an entry has a future post date.  
:green_circle: Element expiry date record is saved when an entry has a future expiry date.  
:green_circle: Element cache IDs are returned when an entry is changed.  
:green_circle: Element cache IDs are returned when an entry is changed by attributes.  
:red_circle: Element cache IDs are not returned when an entry is changed by custom fields.  
:green_circle: Element query cache IDs are returned when a disabled entry is changed.  
:green_circle: Element query type records are returned when an entry is changed.  
:green_circle: Element query type records without a cache ID are not returned when an entry is changed.  
:green_circle: Element query type records are returned when an entry is changed by attributes used in the query.  
:green_circle: Element query type records are not returned when an entry is changed by attributes not used in the query.  
:green_circle: Element query type records are returned when an entry is changed by custom fields used in the query.  
:green_circle: Element query type records are not returned when an entry is changed by custom fields not used in the query.  
:green_circle: Element query type records are returned when an entry is changed with the date updated used in the query.  

### [SiteUri](pest/Feature/SiteUriTest.php)

> _Tests the site URI helper methods._

:green_circle: Site URIs are returned from assets with transforms.  
:green_circle: HTML mime type is returned when site URI is HTML.  
:green_circle: JSON mime type is returned when site URI is JSON.  
:green_circle: Site URIs with page triggers are paginated.  
:green_circle: Site URIs without page triggers are not paginated.  

## Integration Tests

### [Commerce](pest/Integration/CommerceTest.php)

> _Tests that Commerce variants are refreshed on order completion so that their stock is updated._

:green_circle: Variants are refreshed on order completion.  

### [FeedMe](pest/Integration/FeedMeTest.php)

> _Tests that Feed Me imports refresh the cache with batch mode enabled._

:green_circle: Cache is refreshed with batch mode enabled.  

### [Seomatic](pest/Integration/SeomaticTest.php)

> _Tests that cached pages are refreshed when SEOmatic meta containers are invalidated._

:green_circle: Invalidate container caches event without a URL or source triggers a refresh all.  
:green_circle: Invalidate container caches event with a specific source triggers a refresh.  
:green_circle: Invalidate container caches event for a specific element does not trigger a refresh.  

## Interface Tests

### [WebResponse](pest/Interface/WebResponseTest.php)

> _Tests that cached web responses contain the correct headers and comments._

:green_circle: Response adds `X-Powered-By` header once.  
:green_circle: Response overwrites `X-Powered-By` header.  
:green_circle: Response contains output comments when enabled.  
:green_circle: Response does not contain output comments when disabled.  
:green_circle: Response with mime type has headers and does not contain output comments.  
:green_circle: Response is encoded when compression is enabled.  
:green_circle: Response is not encoded when compression is disabled.  
