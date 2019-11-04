# Testing

To test the plugin, install Codeception, update `.env` and run the following command from the root of your project.

    codecept run -c ./vendor/putyourlightson/craft-blitz

Or to run a specific test.

     codecept run -c ./vendor/putyourlightson/craft-blitz unit services/GenerateCacheTest:cacheSaved

> Ensure that the database you specify in `.env` is not one that actually contains any data as it will be cleared when the tests are run. 
