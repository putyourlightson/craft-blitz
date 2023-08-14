# Test Specification

This document outlines the test specification for the Blitz plugin.

---

## Feature Tests

### [CacheRequest](pest/Feature/CacheRequestTest.php)

> _Tests whether requests are cacheable and under what circumstances._

☑ Request matching included URI pattern is cacheable.  
☑ Request with generate token is cacheable.  
☑ Request with `no-cache` param is not cacheable.  
☑ Request with token is not cacheable.  
☑ Request with `_includes` path is a cached include.  
☑ Request with include action is a cached include.  
☑ Requested cacheable site URI includes allowed query strings when urls cached as unique pages.  
☑ Requested cacheable site URI does not include query strings when urls cached as same page.  
☑ Requested cacheable site URI includes page trigger.  
☑ Requested cacheable site URI works with regular expressions.  
☑ Site URI with included URI pattern is cacheable.  
☑ Site URI with excluded URI pattern is not cacheable.  
☑ Site URI with `admin` in URI is cacheable.  
☑ Site URI with `index.php` in URI is not cacheable.  
☑ Site URI with max URI length is cacheable.  
☑ Site URI with max URI length exceeded is not cacheable.  
☑ URI patterns with matching regular expressions are matched.  
☑ URI patterns without matching regular expressions are not matched.  

### [CacheStorage](pest/Feature/CacheStorageTest.php)

> _Tests the storing of cached values using the cache storage drivers._

☑ 255 character site URI can be saved with data set "FileStorage".  
☑ 255 character site URI can be saved with data set "YiiCacheStorage".  
☑ 255 character site URI can be saved with data set "RedisStorage".  
☑ Long site URI can be saved except for by file storage driver with data set "FileStorage".  
☑ Long site URI can be saved except for by file storage driver with data set "YiiCacheStorage".  
☑ Long site URI can be saved except for by file storage driver with data set "RedisStorage".  
☑ Site URI is decoded before being saved with data set "FileStorage".  
☑ Site URI is decoded before being saved with data set "YiiCacheStorage".  
☑ Site URI is decoded before being saved with data set "RedisStorage".  
☑ Compressed cached value can be fetched compressed and uncompressed with data set "FileStorage".  
☑ Compressed cached value can be fetched compressed and uncompressed with data set "YiiCacheStorage".  
☑ Compressed cached value can be fetched compressed and uncompressed with data set "RedisStorage".  
☑ Cached value of site URI can be deleted with data set "FileStorage".  
☑ Cached value of site URI can be deleted with data set "YiiCacheStorage".  
☑ Cached value of site URI can be deleted with data set "RedisStorage".  
☑ Cached value of decoded site URI can be deleted with data set "FileStorage".  
☑ Cached value of decoded site URI can be deleted with data set "YiiCacheStorage".  
☑ Cached value of decoded site URI can be deleted with data set "RedisStorage".  
☑ All cached values can be deleted with data set "FileStorage".  
☑ All cached values can be deleted with data set "YiiCacheStorage".  
☑ All cached values can be deleted with data set "RedisStorage".  

### [GenerateCache](pest/Feature/GenerateCacheTest.php)

> _Tests the saving of cached values, element cache records and element query records._

☑ Cached value is saved with output comments.  
☑ Cached value is saved without output comments.  
☑ Cached value is saved with output comments when file extension is `.html`.  
☑ Cached value is saved without output comments when file extension is not `.html`.  
☑ Cache record with max URI length is saved.  
☑ Cache record with max URI length exceeded throws exception.  
☑ Element cache record is saved without custom fields.  
☑ Element cache record is saved with custom fields.  
☑ Element cache record is saved with eager loaded custom fields.  
☑ Element cache record is saved with eager loaded custom fields in variable.  
☑ Element query records without specific identifiers are saved.  
☑ Element query records with specific identifiers are not saved.  
☑ Element query record with join is saved.  
☑ Element query record with relation field is not saved.  
☑ Element query record with related to param is saved.  
☑ Element query record with expression is not saved.  
☑ Element query cache records are saved.  
☑ Element query source records with specific source identifiers are saved.  
☑ Element query source records without specific source identifiers are not saved.  
☑ Element query attribute records are saved.  
☑ Element query attribute records are saved with order by.  
☑ Element query attribute records are saved with order by parts array.  
☑ Element query attribute records are saved with before.  
☑ Element query field records are saved with order by.  
☑ Element query field records are saved with order by array.  
☑ Cache tags are saved.  
☑ Include record is saved.  
☑ SSI include cache record is saved.  

### [RefreshCache](pest/Feature/RefreshCacheTest.php)

> _Tests the tracking of changes to elements and the resulting element cache IDs and element query type records._

☑ Element is not tracked when it is unchanged.  
☑ Element is tracked when `refreshCacheWhenElementSavedUnchanged` is `true` and it is unchanged.  
☑ Element is not tracked when disabled and its attribute is changed.  
☑ Element is tracked when disabled and `refreshCacheWhenElementSavedNotLive` is `true` and its attribute is changed.  
☑ Element is tracked when its status is changed.  
☑ Element is tracked when it expires.  
☑ Element is tracked when it is deleted.  
☑ Element is tracked when its attribute is changed.  
☑ Element is tracked when its field is changed.  
☑ Element is tracked when its attribute and field are changed.  
☑ Element is tracked when its status and attribute and field are changed.  
☑ Asset is tracked when its file is replaced.  
☑ Asset is tracked when its filename is changed.  
☑ Asset is tracked when its focal point is changed.  
☑ Element expiry date record is saved when an entry has a future post date.  
☑ Element expiry date record is saved when an entry has a future expiry date.  
☑ Element cache IDs are returned when an entry is changed.  
☑ Element cache IDs are returned when an entry is changed by attributes.  
<span style="color: #d81e23;">☒ Element cache IDs are not returned when an entry is changed by custom fields.</span>  
☑ Element query cache IDs are returned when a disabled entry is changed.  
☑ Element query type records are returned when an entry is changed.  
☑ Element query type records without a cache ID are not returned when an entry is changed.  
☑ Element query type records are returned when an entry is changed by attributes used in the query.  
☑ Element query type records are not returned when an entry is changed by attributes not used in the query.  
☑ Element query type records are returned when an entry is changed by custom fields used in the query.  
☑ Element query type records are not returned when an entry is changed by custom fields not used in the query.  
☑ Element query type records are returned when an entry is changed with the date updated used in the query.  

### [SiteUri](pest/Feature/SiteUriTest.php)

> _Tests the site URI helper methods._

☑ Site URIs are returned from assets with transforms.  
☑ HTML mime type is returned when site URI is HTML.  
☑ JSON mime type is returned when site URI is JSON.  
☑ Site URIs with page triggers are paginated.  
☑ Site URIs without page triggers are not paginated.  

## Integration Tests

### [Commerce](pest/Integration/CommerceTest.php)

> _Tests that Commerce variants are refreshed on order completion so that their stock is updated._

☑ Variants are refreshed on order completion.  

### [FeedMe](pest/Integration/FeedMeTest.php)

> _Tests that Feed Me imports refresh the cache with batch mode enabled._

☑ Cache is refreshed with batch mode enabled.  

### [Seomatic](pest/Integration/SeomaticTest.php)

> _Tests that cached pages are refreshed when SEOmatic meta containers are invalidated._

☑ Invalidate container caches event without a URL or source triggers a refresh all.  
☑ Invalidate container caches event with a specific source triggers a refresh.  
☑ Invalidate container caches event for a specific element does not trigger a refresh.  

## Interface Tests

### [WebResponse](pest/Interface/WebResponseTest.php)

> _Tests that cached web responses contain the correct headers and comments._

☑ Response adds `X-Powered-By` header once.  
☑ Response overwrites `X-Powered-By` header.  
☑ Response contains output comments when enabled.  
☑ Response does not contain output comments when disabled.  
☑ Response with mime type has headers and does not contain output comments.  
☑ Response is encoded when compression is enabled.  
☑ Response is not encoded when compression is disabled.  
