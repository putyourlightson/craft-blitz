<?php

namespace putyourlightson\blitztests\Support;

use craft\helpers\FileHelper;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\drivers\deployers\BaseDeployer;
use putyourlightson\blitz\models\SiteUriModel;

class DescendantUriDeployer extends BaseDeployer
{
    public string $folder;

    public function deployUrisWithProgress(array $siteUris, callable $setProgressHandler = null): void
    {
        foreach ($siteUris as $siteUri) {
            $siteUri = is_array($siteUri) ? new SiteUriModel($siteUri) : $siteUri;
            $path = $this->folder . '/' . $siteUri->siteId . '/' . $siteUri->uri . '/index.html';
            $output = Blitz::$plugin->cacheStorage->get($siteUri);
            if ($output !== null) {
                FileHelper::writeToFile($path, $output);
            } elseif (is_file($path)) {
                unlink($path);
            }
        }
    }
}
