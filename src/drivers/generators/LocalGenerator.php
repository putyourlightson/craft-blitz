<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\drivers\generators;

use Craft;
use craft\helpers\UrlHelper;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\helpers\CacheGeneratorHelper;
use putyourlightson\blitz\models\SiteUriModel;
use putyourlightson\blitz\services\CacheRequestService;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

class LocalGenerator extends BaseCacheGenerator
{
    /**
     * @see _getPhpPath()
     */
    private string|bool|null $_phpPath = null;

    /**
     * @see _getToken()
     */
    private string|bool|null $_token = null;

    /**
     * @see _getWebroot()
     */
    private string|bool|null $_webroot = null;

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('blitz', 'Local Generator');
    }

    /**
     * @inheritdoc
     */
    public function generateUris(array $siteUris, callable $setProgressHandler = null, bool $queue = true)
    {
        $siteUris = $this->beforeGenerateCache($siteUris);

        if (empty($siteUris)) {
            return;
        }

        if ($queue) {
            CacheGeneratorHelper::addGeneratorJob($siteUris, 'generateUrisWithProgress');
        }
        else {
            $this->generateUrisWithProgress($siteUris, $setProgressHandler);
        }

        $this->afterGenerateCache($siteUris);
    }

    /**
     * Generates site URIs with progress.
     */
    public function generateUrisWithProgress(array $siteUris, callable $setProgressHandler = null)
    {
        $count = 0;
        $total = count($siteUris);
        $label = 'Generateing {count} of {total} pages.';

        foreach ($siteUris as $siteUri) {
            $count++;

            if (is_callable($setProgressHandler)) {
                $progressLabel = Craft::t('blitz', $label, ['count' => $count, 'total' => $total]);
                call_user_func($setProgressHandler, $count, $total, $progressLabel);
            }

            // Convert to a SiteUriModel if it is an array
            if (is_array($siteUri)) {
                $siteUri = new SiteUriModel($siteUri);
            }

            $success = $this->_generateUri($siteUri);

            if ($success) {
                $this->generated++;
            }
        }
    }

    /**
     * Generates a site URI.
     */
    private function _generateUri(SiteUriModel $siteUri): bool
    {
        // Only proceed if this is a cacheable site URI
        if (!Blitz::$plugin->cacheRequest->getIsCacheableSiteUri($siteUri)) {
            return false;
        }

        $url = UrlHelper::url($siteUri->getUrl(), [
            'token' => $this->_getToken(),
        ]);

        $command = [
            $this->_getPhpPath(),
            CRAFT_VENDOR_PATH . '/putyourlightson/craft-blitz/src/web/bootstrap.php',
            '--url=' . $url,
            '--webroot=' . $this->_getWebroot(),
            '--basePath=' . CRAFT_BASE_PATH,
        ];
        $cwd = realpath(CRAFT_BASE_PATH);

        $process = new Process($command, $cwd);
        $process->run();

        return $process->getOutput() == 1;
    }

    private function _getPhpPath(): string|bool
    {
        if ($this->_phpPath !== null) {
            return $this->_phpPath;
        }

        $phpFinder = new PhpExecutableFinder();
        $this->_phpPath = $phpFinder->find();

        return $this->_phpPath;
    }

    private function _getToken(): string|bool
    {
        if ($this->_token !== null) {
            return $this->_token;
        }

        $this->_token = Craft::$app->getTokens()->createToken([CacheRequestService::GENERATE_ROUTE, [
            'output' => false,
        ]]);

        return $this->_token;
    }

    private function _getWebroot(): string|bool
    {
        if ($this->_webroot !== null) {
            return $this->_webroot;
        }

        $this->_webroot = Craft::getAlias('@webroot');

        return $this->_webroot;
    }
}
