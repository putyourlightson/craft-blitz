<?php

/**
 * Tests base generator functionality.
 */

use putyourlightson\blitz\drivers\generators\HttpGenerator;
use putyourlightson\blitz\services\CacheRequestService;

test('Getting URLs to generate ignores uniquely cached includes', function() {
    $generator = new HttpGenerator();
    $urls = $generator->getUrlsToGenerate([
        [
            'siteId' => 1,
            'uri' => CacheRequestService::CACHED_INCLUDE_URI_PREFIX,
        ],
        [
            'siteId' => 1,
            'uri' => CacheRequestService::CACHED_INCLUDE_URI_PREFIX . 'uid=1234567890',
        ],
    ]);

    expect($urls)
        ->toHaveCount(1);
});
