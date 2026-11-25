<?php

/**
 * Tests functionality specific to the file storage driver.
 */

use putyourlightson\blitz\drivers\storage\FileStorage;
use putyourlightson\blitz\models\SiteUriModel;

test('Getting a site path works for a disabled or non-existent site', function() {
    $cacheStorage = new FileStorage();

    expect($cacheStorage->getSitePath(1234))
        ->toBeNull();
});

test('File paths remain within the site cache directory', function(string $uri, array $expectedPaths) {
    $cacheStorage = new class() extends FileStorage {
        public function getSitePath(int $siteId): ?string
        {
            return '/tmp/blitz-cache/example.com';
        }
    };

    $siteUri = new SiteUriModel(['siteId' => 1, 'uri' => $uri]);

    expect($cacheStorage->getFilePaths($siteUri))
        ->toBe($expectedPaths);
})->with([
    'normal URI' => ['pages/news', ['/tmp/blitz-cache/example.com/pages/news/index.html']],
    'encoded filename' => ['pages/caf%C3%A9', [
        '/tmp/blitz-cache/example.com/pages/caf%C3%A9/index.html',
        '/tmp/blitz-cache/example.com/pages/café/index.html',
    ]],
    'encoded traversal' => ['pages/%2e%2e%2f%2e%2e%2fsecret', [
        '/tmp/blitz-cache/example.com/pages/%2e%2e%2f%2e%2e%2fsecret/index.html',
    ]],
    'encoded sibling prefix' => ['pages/%2e%2e%2f%2e%2e%2fexample.com-sibling', [
        '/tmp/blitz-cache/example.com/pages/%2e%2e%2f%2e%2e%2fexample.com-sibling/index.html',
    ]],
    'sibling prefix' => ['../../example.com-sibling', []],
    'overlong segment' => [str_repeat('é', 128), []],
    'overlong path' => [str_repeat(str_repeat('a', 200) . '/', intdiv(PHP_MAXPATHLEN, 200) + 1), []],
    'only the decoded filename fits' => [str_repeat('%C3%A9', 100), [
        '/tmp/blitz-cache/example.com/' . str_repeat('é', 100) . '/index.html',
    ]],
]);
