# Testing

## Static Analysis

To run static analysis on the plugin, install [PHPStan for Craft CMS](https://github.com/craftcms/phpstan) and run the following command from the root of your project.

```shell
./vendor/bin/phpstan analyse -c vendor/putyourlightson/craft-blitz/phpstan.neon  --memory-limit 1G
```

## Easy Coding Standard

To run the Easy Coding Standard on the plugin, install [ECS for Craft CMS](https://github.com/craftcms/ecs) and run the following command from the root of your project.

```shell
 ./vendor/bin/ecs check -c vendor/putyourlightson/craft-blitz/ecs.php
```

## Unit Tests


To unit test the plugin, install Codeception, update `.env` and add the following autoload namespace to the project’s main `composer.json` file.

```
    "autoload-dev": {
        "psr-4": {
          "putyourlightson\\blitztests\\": "vendor/putyourlightson/craft-blitz/tests/"
        }
    },
```

Then run the following command from the root of your project.

```shell
./vendor/bin/codecept run -c vendor/putyourlightson/craft-blitz unit
```

Or to run a specific test.

```shell
./vendor/bin/codecept run -c ./vendor/putyourlightson/craft-blitz unit services/GenerateCacheTest:cacheSaved
```

> Ensure that the database you specify in `.env` is not one that actually contains any data as it will be cleared when the tests are run. 
