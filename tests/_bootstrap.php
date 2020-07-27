<?php

use craft\test\TestSetup;

ini_set('date.timezone', 'UTC');

define('CRAFT_TESTS_PATH', __DIR__);
define('CRAFT_STORAGE_PATH', __DIR__ . '/_craft/storage');
define('CRAFT_TEMPLATES_PATH', __DIR__ . '/_craft/templates');
define('CRAFT_CONFIG_PATH', __DIR__ . '/_craft/config');
define('CRAFT_MIGRATIONS_PATH', __DIR__ . '/_craft/migrations');
define('CRAFT_TRANSLATIONS_PATH', __DIR__ . '/_craft/translations');
//define('CRAFT_VENDOR_PATH', dirname(__DIR__, 3));

// Use absolute path if the plugin directory is a symlink
define('CRAFT_VENDOR_PATH', '/Users/ben/Sites/craft3/vendor');

$devMode = true;

TestSetup::configureCraft();

// Prevent `headers already sent` error
ob_start();
