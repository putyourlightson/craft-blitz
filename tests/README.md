# Testing

## Static Analysis

To run static analysis on the plugin,
install [PHPStan for Craft CMS](https://github.com/craftcms/phpstan) and run the
following command from the root of your project.

```shell
./vendor/bin/phpstan analyse -c vendor/putyourlightson/craft-blitz/phpstan.neon  --memory-limit 1G
```

## Easy Coding Standard

To run the Easy Coding Standard on the plugin,
install [ECS for Craft CMS](https://github.com/craftcms/ecs) and run the
following command from the root of your project.

```shell
./vendor/bin/ecs check -c vendor/putyourlightson/craft-blitz/ecs.php
```

## Pest Tests

To run Pest tests, install [Craft Pest](https://craft-pest.com/) and run the
following command from the root of your project.

```shell
php craft pest/test --test-directory=vendor/putyourlightson/craft-blitz/tests/pest
```

Or to run a specific test.

```shell
php craft pest/test --test-directory=vendor/putyourlightson/craft-blitz/tests/pest --filter=CacheRequestTest
```

## Codeception Tests (legacy)

> Codeception tests are being phased out in place of Pest tests.

To run Codeception tests, install Codeception, update `.env` and add the
following autoload namespace to
the project’s main `composer.json` file.

```
    "autoload-dev": {
        "psr-4": {
          "putyourlightson\\blitztests\\": "vendor/putyourlightson/craft-blitz/tests/codeception/"
        }
    },
```

Then run the following command from the root of your project.

```shell
./vendor/bin/codecept run -c vendor/putyourlightson/craft-blitz/tests/codeception unit
```

Or to run a specific test.

```shell
./vendor/bin/codecept run -c ./vendor/putyourlightson/craft-blitz/tests/codeception unit services/GenerateCacheTest:cacheSaved
```

> Ensure that the database you specify in `.env` is not one that actually
> contains any data as it will be cleared when the tests are run. 
