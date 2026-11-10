<?php

use craft\events\CancelableEvent;
use craft\helpers\FileHelper;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\drivers\deployers\GitDeployer;
use putyourlightson\blitz\drivers\storage\FileStorage;
use putyourlightson\blitz\models\SiteUriModel;

test('Git deployment cannot delete files outside the repository', function(string $uri) {
    $root = sys_get_temp_dir() . '/blitz-deployer-' . bin2hex(random_bytes(8));
    $repositoryPath = $root . '/repository';
    FileHelper::createDirectory($repositoryPath);
    FileHelper::writeToFile($root . '/outside.txt', 'Outside repository');
    FileHelper::writeToFile($repositoryPath . '/inside.txt', 'Inside repository');

    $site = Craft::$app->getSites()->getPrimarySite();
    $cacheStorage = Blitz::$plugin->cacheStorage;
    Blitz::$plugin->set('cacheStorage', new class() extends FileStorage {
        public function get(SiteUriModel $siteUri): ?string
        {
            return null;
        }
    });

    try {
        $deployer = new GitDeployer([
            'gitRepositories' => [
                $site->uid => ['repositoryPath' => $repositoryPath, 'branch' => '', 'remote' => ''],
            ],
        ]);
        $deployer->on(GitDeployer::EVENT_BEFORE_COMMIT, function(CancelableEvent $event) {
            $event->isValid = false;
        });
        $deployer->deployUrisWithProgress([
            new SiteUriModel(['siteId' => $site->id, 'uri' => $uri]),
            new SiteUriModel(['siteId' => $site->id, 'uri' => 'inside.txt']),
        ]);

        expect(file_get_contents($root . '/outside.txt'))->toBe('Outside repository')
            ->and(file_exists($repositoryPath . '/inside.txt'))->toBeFalse();
    } finally {
        Blitz::$plugin->set('cacheStorage', $cacheStorage);
        FileHelper::removeDirectory($root);
    }
})->with([
    '../outside.txt',
    'pages/..\\..\\outside.txt',
]);
