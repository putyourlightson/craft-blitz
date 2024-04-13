# Test Specification

This document outlines the test specification for the Blitz plugin.

---

## Interface Tests

### [WebResponse](pest/Interface/WebResponseTest.php)

_Tests that cached web responses contain the correct headers and comments._

![Pass](https://raw.githubusercontent.com/putyourlightson/craft-generate-test-spec/main/icons/pass.svg) Response contains the default cache control header when the page is not cacheable.  
![Pass](https://raw.githubusercontent.com/putyourlightson/craft-generate-test-spec/main/icons/pass.svg) Response contains the cache control header when the page is cacheable.  
![Pass](https://raw.githubusercontent.com/putyourlightson/craft-generate-test-spec/main/icons/pass.svg) Response contains the expired cache control header and the cache is refreshed when the page is expired.  
![Pass](https://raw.githubusercontent.com/putyourlightson/craft-generate-test-spec/main/icons/pass.svg) Response adds the powered by header.  
![Pass](https://raw.githubusercontent.com/putyourlightson/craft-generate-test-spec/main/icons/pass.svg) Response with mime type has headers and does not contain output comments.  
![Pass](https://raw.githubusercontent.com/putyourlightson/craft-generate-test-spec/main/icons/pass.svg) Response is encoded when compression is enabled.  
