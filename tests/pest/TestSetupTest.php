<?php

/**
 * Tests whether the test setup is correct.
 */

use putyourlightson\blitz\Blitz;

test('Included URI patterns include the home and `page` URI', function() {
    expect(Blitz::$plugin->settings->includedUriPatterns)
        ->toContain([
            'siteId' => '',
            'uriPattern' => '',
        ])
        ->toContain([
            'siteId' => '',
            'uriPattern' => 'page',
        ]);
});
