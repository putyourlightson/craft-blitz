<?php

use craft\fs\Local;
use putyourlightson\blitztests\fixtures\FsFixture;

return [
    'localFs' => [
        'id' => '1000',
        'name' => 'Local FS',
        'type' => Local::class,
        'url' => null,
        'hasUrls' => true,
        'settings' => [
            'path' => dirname(__FILE__, 3) . '/_data/assets/volume-folder-1/',
            'url' => FsFixture::BASE_URL,
        ],
    ],
];
