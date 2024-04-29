# Test Specification

This document outlines the test specification for the Blitz plugin.

---

## Feature Tests

### [CacheRequest](pest/Feature/CacheRequestTest.php)

_Tests whether requests are cacheable and under what circumstances._

![Pass](https://raw.githubusercontent.com/putyourlightson/craft-generate-test-spec/main/icons/pass.svg) Request matching included URI pattern is cacheable.  
![Pass](https://raw.githubusercontent.com/putyourlightson/craft-generate-test-spec/main/icons/pass.svg) URI patterns with matching regular expressions are matched.  
![Pass](https://raw.githubusercontent.com/putyourlightson/craft-generate-test-spec/main/icons/pass.svg) URI patterns without matching regular expressions are not matched.  

### [GenerateCache](pest/Feature/GenerateCacheTest.php)

_Tests the saving of cached values, element cache records and element query records._

![Pass](https://raw.githubusercontent.com/putyourlightson/craft-generate-test-spec/main/icons/pass.svg) Element query cache records with matching params and a higher limit and offset sum are the only ones saved with data set "([1], [10])".  
![Pass](https://raw.githubusercontent.com/putyourlightson/craft-generate-test-spec/main/icons/pass.svg) Element query cache records with matching params and a higher limit and offset sum are the only ones saved with data set "([1, 1], [10, 10])".  
![Pass](https://raw.githubusercontent.com/putyourlightson/craft-generate-test-spec/main/icons/pass.svg) Element query cache records with matching params and a higher limit and offset sum are the only ones saved with data set "([1, 10], [10, 1])".  
![Pass](https://raw.githubusercontent.com/putyourlightson/craft-generate-test-spec/main/icons/pass.svg) Element query cache records with matching params and a higher limit and offset sum are the only ones saved with data set "([10, 1], [1, 20])".  
