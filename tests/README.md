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

To run Pest tests, first install [Craft Pest](https://craft-pest.com/) core as a dev dependency.

```shell
composer require markhuot/craft-pest-core:^2.0.0-rc2 --dev
```

Then run the following command from the root of your project.

```shell
php vendor/bin/pest --configuration=vendor/putyourlightson/craft-blitz/tests/pest/phpunit.xml --test-directory=vendor/putyourlightson/craft-blitz/tests/pest
```

Or to run a specific test.

```shell
php vendor/bin/pest --configuration=vendor/putyourlightson/craft-blitz/tests/pest/phpunit.xml --test-directory=vendor/putyourlightson/craft-blitz/tests/pest --filter=CacheRequestTest
```
