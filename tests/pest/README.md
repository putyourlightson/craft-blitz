# Testing

## Usage

1. Install the [Craft Pest](https://craft-pest.com) plugin.
    ```shell
    composer require-dev markhuot/craft-pest --dev
    php craft plugin/install pest
    ```
2. Copy `phpunit.xml` to the root of your project.
3. Execute the following command from the root of your project.
    ```shell
    php craft pest/test --test-directory=vendor/putyourlightson/craft-blitz/pest-tests
    ```
