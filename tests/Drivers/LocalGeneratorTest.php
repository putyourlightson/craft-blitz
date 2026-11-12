<?php

use craft\helpers\FileHelper;
use craft\helpers\Json;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\drivers\generators\LocalGenerator;
use putyourlightson\blitz\models\SiteUriModel;
use putyourlightson\blitz\records\CacheRecord;
use putyourlightson\blitz\records\IncludeRecord;
use putyourlightson\blitz\services\CacheRequestService;

test('Local Generator creates and regenerates cached includes through a web request', function() {
    $site = Craft::$app->getSites()->getPrimarySite();
    $template = '_blitz-local-generator-' . bin2hex(random_bytes(8));
    $templatePath = Craft::$app->getPath()->getSiteTemplatesPath() . '/' . $template . '.twig';
    FileHelper::writeToFile($templatePath, '{{ marker }}');
    $siteUri = null;
    $includeId = null;

    try {
        [$includeId, $index] = Blitz::$plugin->generateCache->saveInclude($site->id, $template, [
            'marker' => 'Initial cached include',
        ]);
        $uri = CacheRequestService::CACHED_INCLUDE_PREFIX . $index;
        $siteUri = new SiteUriModel([
            'siteId' => $site->id,
            'uri' => $uri . '?' . Blitz::$plugin->settings->cachedIncludePathParam . '=' . $uri,
        ]);
        $generator = new LocalGenerator();

        expect(Blitz::$plugin->cacheStorage->get($siteUri))->toBeNull();

        $generator->generateUris([$siteUri], queue: false);

        expect($generator->generated)->toBe(1)
            ->and(Blitz::$plugin->cacheStorage->get($siteUri))->toContain('Initial cached include')
            ->and(CacheRecord::find()->where($siteUri->toArray())->exists())->toBeTrue();

        // Keep the URL and existing cached value, and change the rendered content.
        IncludeRecord::updateAll([
            'params' => Json::encode(['marker' => 'Regenerated cached include']),
        ], ['id' => $includeId]);
        $generator->generateUris([$siteUri], queue: false);

        expect($generator->generated)->toBe(2)
            ->and(Blitz::$plugin->cacheStorage->get($siteUri))
            ->toContain('Regenerated cached include')
            ->not()->toContain('Initial cached include');
    } finally {
        if ($siteUri !== null) {
            Blitz::$plugin->cacheStorage->deleteUris([$siteUri]);
            CacheRecord::deleteAll($siteUri->toArray());
        }
        if ($includeId !== null) {
            IncludeRecord::deleteAll(['id' => $includeId]);
        }
        unlink($templatePath);
    }
});
