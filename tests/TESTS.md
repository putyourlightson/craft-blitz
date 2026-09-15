# Test Specification

This document outlines the test specification for the Blitz plugin.

---

## Feature Tests

### [CacheRequest](Feature/CacheRequestTest.php)

_Tests whether requests are cacheable and under what circumstances._

![Pass](https://raw.githubusercontent.com/putyourlightson/craft-generate-test-spec/main/icons/pass.svg) Requested cacheable site URI works with regular expressions.  
![Pass](https://raw.githubusercontent.com/putyourlightson/craft-generate-test-spec/main/icons/pass.svg) URI patterns with matching regular expressions are matched.  
![Pass](https://raw.githubusercontent.com/putyourlightson/craft-generate-test-spec/main/icons/pass.svg) URI patterns without matching regular expressions are not matched.  

### [GenerateCache](Feature/GenerateCacheTest.php)

_Tests the saving of cached values, element cache records and element query records._

![Pass](https://raw.githubusercontent.com/putyourlightson/craft-generate-test-spec/main/icons/pass.svg) Element query record with expression is not saved.  
![Pass](https://raw.githubusercontent.com/putyourlightson/craft-generate-test-spec/main/icons/pass.svg) SSI include cache record is saved.  

### [RefreshCache](Feature/RefreshCacheTest.php)

_Tests the tracking of changes to elements and the resulting element cache IDs and element query type records._

![Pass](https://raw.githubusercontent.com/putyourlightson/craft-generate-test-spec/main/icons/pass.svg) SSI include site URIs are returned for a cached include site URI.  
![Pass](https://raw.githubusercontent.com/putyourlightson/craft-generate-test-spec/main/icons/pass.svg) SSI include site URIs are returned for a legacy cached include site URI.  
![Pass](https://raw.githubusercontent.com/putyourlightson/craft-generate-test-spec/main/icons/pass.svg) SSI include site URIs are not returned for a site URI that is not a cached include.  

## Interface Tests

### [WebResponse](Interface/WebResponseTest.php)

_Tests that cached web responses contain the correct headers and comments._

![Pass](https://raw.githubusercontent.com/putyourlightson/craft-generate-test-spec/main/icons/pass.svg) Response is encoded when compression is enabled.  
