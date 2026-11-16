<?php

use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\drivers\deployers\BaseDeployer;
use putyourlightson\blitz\events\RefreshCacheEvent;
use putyourlightson\blitz\models\SiteUriModel;

test('Deploying all pages includes custom site URIs and preserves deploy events', function() {
    $settings = Blitz::$plugin->settings;
    $customSiteUris = $settings->customSiteUris;
    $siteUri = new SiteUriModel([
        'siteId' => Craft::$app->getSites()->getPrimarySite()->id,
        'uri' => 'custom-deploy-' . bin2hex(random_bytes(8)),
    ]);
    $settings->customSiteUris = [$siteUri->toArray()];
    $deployer = new class() extends BaseDeployer {
        public array $deployed = [];

        public function deployUrisWithProgress(array $siteUris, callable $setProgressHandler = null): void
        {
            $this->deployed = $siteUris;
        }
    };
    $events = [];
    foreach ([BaseDeployer::EVENT_BEFORE_DEPLOY_ALL, BaseDeployer::EVENT_BEFORE_DEPLOY, BaseDeployer::EVENT_AFTER_DEPLOY, BaseDeployer::EVENT_AFTER_DEPLOY_ALL] as $name) {
        $deployer->on($name, function(RefreshCacheEvent $event) use (&$events) {
            $events[] = $event->name;
        });
    }

    try {
        $deployer->deployAll(queue: false);

        expect(in_array($siteUri, $deployer->deployed))->toBeTrue()
            ->and($events)->toBe([
                BaseDeployer::EVENT_BEFORE_DEPLOY_ALL,
                BaseDeployer::EVENT_BEFORE_DEPLOY,
                BaseDeployer::EVENT_AFTER_DEPLOY,
                BaseDeployer::EVENT_AFTER_DEPLOY_ALL,
            ]);
    } finally {
        $settings->customSiteUris = $customSiteUris;
    }
});
