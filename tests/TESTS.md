# Test Specification

This document outlines the test specification for the Blitz plugin.

---

## Feature Tests

### [Settings](pest/Feature/SettingsTest.php)

_Tests the settings methods._

![Pass](https://raw.githubusercontent.com/putyourlightson/craft-generate-test-spec/main/icons/pass.svg) The cache should be cleared on refresh” with data set `clear only`.  
![Pass](https://raw.githubusercontent.com/putyourlightson/craft-generate-test-spec/main/icons/pass.svg) The cache should be cleared on refresh” with data set `clear and generate`.  
![Pass](https://raw.githubusercontent.com/putyourlightson/craft-generate-test-spec/main/icons/pass.svg) The cache should not be cleared on refresh with data set `expire only`.  
![Pass](https://raw.githubusercontent.com/putyourlightson/craft-generate-test-spec/main/icons/pass.svg) The cache should not be cleared on refresh with data set `expire and generate`.  
![Pass](https://raw.githubusercontent.com/putyourlightson/craft-generate-test-spec/main/icons/pass.svg) The cache should be cleared on refresh when forcing a clear with data set `clear only`.  
![Pass](https://raw.githubusercontent.com/putyourlightson/craft-generate-test-spec/main/icons/pass.svg) The cache should be cleared on refresh when forcing a clear with data set `clear and generate`.  
![Pass](https://raw.githubusercontent.com/putyourlightson/craft-generate-test-spec/main/icons/pass.svg) The cache should be generated on refresh with data set `clear and generate`.  
![Pass](https://raw.githubusercontent.com/putyourlightson/craft-generate-test-spec/main/icons/pass.svg) The cache should be generated on refresh with data set `expire and generate`.  
![Pass](https://raw.githubusercontent.com/putyourlightson/craft-generate-test-spec/main/icons/pass.svg) The cache should not be generated on refresh with data set `clear only`.  
![Pass](https://raw.githubusercontent.com/putyourlightson/craft-generate-test-spec/main/icons/pass.svg) The cache should not be generated on refresh with data set `expire only`.  
![Pass](https://raw.githubusercontent.com/putyourlightson/craft-generate-test-spec/main/icons/pass.svg) The cache should be generated on refresh when forcing a generate with data set `clear only`.  
![Pass](https://raw.githubusercontent.com/putyourlightson/craft-generate-test-spec/main/icons/pass.svg) The cache should be generated on refresh when forcing a generate with data set `expire only`.  
![Pass](https://raw.githubusercontent.com/putyourlightson/craft-generate-test-spec/main/icons/pass.svg) The cache should be expired on refresh with data set `expire only`.  
![Pass](https://raw.githubusercontent.com/putyourlightson/craft-generate-test-spec/main/icons/pass.svg) The cache should be expired on refresh with data set `expire and generate`.  
![Pass](https://raw.githubusercontent.com/putyourlightson/craft-generate-test-spec/main/icons/pass.svg) The cache should not be expired on refresh with data set `clear only`.  
![Pass](https://raw.githubusercontent.com/putyourlightson/craft-generate-test-spec/main/icons/pass.svg) The cache should not be expired on refresh with data set `clear and generate`.  
![Pass](https://raw.githubusercontent.com/putyourlightson/craft-generate-test-spec/main/icons/pass.svg) The cache should not be expired on refresh when forcing a generate with data set `expire only`.  
![Pass](https://raw.githubusercontent.com/putyourlightson/craft-generate-test-spec/main/icons/pass.svg) The cache should not be expired on refresh when forcing a generate with data set `expire and generate`.  
![Pass](https://raw.githubusercontent.com/putyourlightson/craft-generate-test-spec/main/icons/pass.svg) The cache should be purged after refresh with data set `expire only`.  
![Pass](https://raw.githubusercontent.com/putyourlightson/craft-generate-test-spec/main/icons/pass.svg) The cache should be purged after refresh with data set `expire and generate`.  
![Pass](https://raw.githubusercontent.com/putyourlightson/craft-generate-test-spec/main/icons/pass.svg) The cache should not be purged after refresh with data set `clear only`.  
![Pass](https://raw.githubusercontent.com/putyourlightson/craft-generate-test-spec/main/icons/pass.svg) The cache should not be purged after refresh with data set `clear and generate`.  
![Pass](https://raw.githubusercontent.com/putyourlightson/craft-generate-test-spec/main/icons/pass.svg) The cache should not be purged after refresh when forcing a clear with data set `expire only`.  
![Pass](https://raw.githubusercontent.com/putyourlightson/craft-generate-test-spec/main/icons/pass.svg) The cache should not be purged after refresh when forcing a clear with data set `expire and generate`.  
