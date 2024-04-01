# Test Specification

This document outlines the test specification for the Blitz plugin.

---

## Feature Tests

### [GenerateCache](pest/Feature/GenerateCacheTest.php)

_Tests the saving of cached values, element cache records and element query records._

![Pass](https://raw.githubusercontent.com/putyourlightson/craft-generate-test-spec/main/icons/pass.svg) Cached value is saved with output comments.  
![Pass](https://raw.githubusercontent.com/putyourlightson/craft-generate-test-spec/main/icons/pass.svg) Cached value is saved without output comments.  
![Pass](https://raw.githubusercontent.com/putyourlightson/craft-generate-test-spec/main/icons/pass.svg) Cached value is saved with output comments when file extension is ".html".  
![Pass](https://raw.githubusercontent.com/putyourlightson/craft-generate-test-spec/main/icons/pass.svg) Cached value is saved without output comments when file extension is not `.html`.  

## Interface Tests

### [WebResponse](pest/Interface/WebResponseTest.php)

_Tests that cached web responses contain the correct headers and comments._

![Pass](https://raw.githubusercontent.com/putyourlightson/craft-generate-test-spec/main/icons/pass.svg) Response with mime type has headers and does not contain output comments.  
