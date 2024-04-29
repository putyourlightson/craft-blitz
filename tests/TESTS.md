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
![Pass](https://raw.githubusercontent.com/putyourlightson/craft-generate-test-spec/main/icons/pass.svg) Cache record with max URI length is saved.  
![Pass](https://raw.githubusercontent.com/putyourlightson/craft-generate-test-spec/main/icons/pass.svg) Cache record with max URI length exceeded throws exception.  
![Pass](https://raw.githubusercontent.com/putyourlightson/craft-generate-test-spec/main/icons/pass.svg) Element cache record is saved without custom fields.  
![Pass](https://raw.githubusercontent.com/putyourlightson/craft-generate-test-spec/main/icons/pass.svg) Element cache record is saved with custom fields.  
![Pass](https://raw.githubusercontent.com/putyourlightson/craft-generate-test-spec/main/icons/pass.svg) Element cache record is saved with eager-loaded custom fields.  
![Pass](https://raw.githubusercontent.com/putyourlightson/craft-generate-test-spec/main/icons/pass.svg) Element cache record is saved with eager-loaded custom fields in variable.  
![Pass](https://raw.githubusercontent.com/putyourlightson/craft-generate-test-spec/main/icons/pass.svg) Element cache record is saved for preloaded single.  
![Pass](https://raw.githubusercontent.com/putyourlightson/craft-generate-test-spec/main/icons/pass.svg) Element cache record is saved with eager-loaded custom fields for preloaded single.  
![Pass](https://raw.githubusercontent.com/putyourlightson/craft-generate-test-spec/main/icons/pass.svg) Element cache records are saved with all statuses for relation field queries.  
![Pass](https://raw.githubusercontent.com/putyourlightson/craft-generate-test-spec/main/icons/pass.svg) Element cache records are saved with all statuses for eager-loaded relation field queries.  
![Pass](https://raw.githubusercontent.com/putyourlightson/craft-generate-test-spec/main/icons/pass.svg) Element query records without specific identifiers are saved.  
![Pass](https://raw.githubusercontent.com/putyourlightson/craft-generate-test-spec/main/icons/pass.svg) Element query records with specific identifiers are not saved.  
![Pass](https://raw.githubusercontent.com/putyourlightson/craft-generate-test-spec/main/icons/pass.svg) Element query record with join is saved.  
![Pass](https://raw.githubusercontent.com/putyourlightson/craft-generate-test-spec/main/icons/pass.svg) Element query record with relation field is not saved.  
![Pass](https://raw.githubusercontent.com/putyourlightson/craft-generate-test-spec/main/icons/pass.svg) Element query record with related to param is saved.  
![Pass](https://raw.githubusercontent.com/putyourlightson/craft-generate-test-spec/main/icons/pass.svg) Element query record with query param is saved without the param.  
![Pass](https://raw.githubusercontent.com/putyourlightson/craft-generate-test-spec/main/icons/pass.svg) Element query record with expression is not saved.  
![Pass](https://raw.githubusercontent.com/putyourlightson/craft-generate-test-spec/main/icons/pass.svg) Element query record for entry in single sections is not saved.  
![Pass](https://raw.githubusercontent.com/putyourlightson/craft-generate-test-spec/main/icons/pass.svg) Element query record with option field data is converted to value.  
![Pass](https://raw.githubusercontent.com/putyourlightson/craft-generate-test-spec/main/icons/pass.svg) Element query record with multi options field data is converted to array of values.  
![Pass](https://raw.githubusercontent.com/putyourlightson/craft-generate-test-spec/main/icons/pass.svg) Element query record keeps limit and offset params.  
![Pass](https://raw.githubusercontent.com/putyourlightson/craft-generate-test-spec/main/icons/pass.svg) Element query record keeps order by if a limit param is present.  
![Pass](https://raw.githubusercontent.com/putyourlightson/craft-generate-test-spec/main/icons/pass.svg) Element query record keeps order by if an offset param is present.  
![Pass](https://raw.githubusercontent.com/putyourlightson/craft-generate-test-spec/main/icons/pass.svg) Element query record does not keep order by if no limit or offset param is present.  
![Pass](https://raw.githubusercontent.com/putyourlightson/craft-generate-test-spec/main/icons/pass.svg) Element query record respects excluded tracked element query params.  
![Pass](https://raw.githubusercontent.com/putyourlightson/craft-generate-test-spec/main/icons/pass.svg) Element query cache records are saved.  
![Pass](https://raw.githubusercontent.com/putyourlightson/craft-generate-test-spec/main/icons/pass.svg) Element query source records with specific source identifiers are saved.  
![Pass](https://raw.githubusercontent.com/putyourlightson/craft-generate-test-spec/main/icons/pass.svg) Element query source records without specific source identifiers are not saved.  
![Pass](https://raw.githubusercontent.com/putyourlightson/craft-generate-test-spec/main/icons/pass.svg) Entry query source records are saved when only a structure ID is set.  
![Pass](https://raw.githubusercontent.com/putyourlightson/craft-generate-test-spec/main/icons/pass.svg) Element query attribute records are saved.  
![Pass](https://raw.githubusercontent.com/putyourlightson/craft-generate-test-spec/main/icons/pass.svg) Element query attribute records are saved with order by.  
![Pass](https://raw.githubusercontent.com/putyourlightson/craft-generate-test-spec/main/icons/pass.svg) Element query attribute records are saved with order by parts array.  
![Pass](https://raw.githubusercontent.com/putyourlightson/craft-generate-test-spec/main/icons/pass.svg) Element query attribute records are saved with before.  
![Pass](https://raw.githubusercontent.com/putyourlightson/craft-generate-test-spec/main/icons/pass.svg) Element query field records are saved with order by.  
![Pass](https://raw.githubusercontent.com/putyourlightson/craft-generate-test-spec/main/icons/pass.svg) Element query field records are saved with order by array.  
![Pass](https://raw.githubusercontent.com/putyourlightson/craft-generate-test-spec/main/icons/pass.svg) Cache tags are saved.  
![Pass](https://raw.githubusercontent.com/putyourlightson/craft-generate-test-spec/main/icons/pass.svg) Include record is saved.  
![Pass](https://raw.githubusercontent.com/putyourlightson/craft-generate-test-spec/main/icons/pass.svg) SSI include cache record is saved.  
