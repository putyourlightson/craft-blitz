# Test Specification

This document outlines the test specification for the Blitz plugin.

---

## Feature Tests

### [Hints](pest/Feature/HintsTest.php)

_Tests hints functionality._

![Pass](https://raw.githubusercontent.com/putyourlightson/craft-generate-test-spec/main/icons/pass.svg) Hint is recorded for a matrix element query that is lazy-loaded.  
![Pass](https://raw.githubusercontent.com/putyourlightson/craft-generate-test-spec/main/icons/pass.svg) Hint is not recorded for a matrix element query that is lazy eager-loaded.  
![Pass](https://raw.githubusercontent.com/putyourlightson/craft-generate-test-spec/main/icons/pass.svg) Hint is recorded for a related entry query that is lazy-loaded.  
![Pass](https://raw.githubusercontent.com/putyourlightson/craft-generate-test-spec/main/icons/pass.svg) Hint is not recorded for a related entry query that is lazy eager-loaded.  
![Pass](https://raw.githubusercontent.com/putyourlightson/craft-generate-test-spec/main/icons/pass.svg) Hint is recorded for a related category query that is lazy-loaded.  
![Pass](https://raw.githubusercontent.com/putyourlightson/craft-generate-test-spec/main/icons/pass.svg) Hint is not recorded for a related category query that is lazy eager-loaded.  
![Pass](https://raw.githubusercontent.com/putyourlightson/craft-generate-test-spec/main/icons/pass.svg) Hint is not recorded for element queries with reference tags.  
![Pass](https://raw.githubusercontent.com/putyourlightson/craft-generate-test-spec/main/icons/pass.svg) Hint is not recorded for parsed reference tags.  
