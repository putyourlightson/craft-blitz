<?php

/**
 * Tests functionality specific to the file storage driver.
 */

use putyourlightson\blitz\drivers\storage\FileStorage;

test('Getting a site path works for a disabled or non-existent site', function() {
    $cacheStorage = new FileStorage();

    expect($cacheStorage->getSitePath(1234))
        ->toBeNull();
});
