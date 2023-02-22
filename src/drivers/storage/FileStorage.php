<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\drivers\storage;

use Craft;
use craft\helpers\App;
use craft\helpers\FileHelper;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\events\RefreshCacheEvent;
use putyourlightson\blitz\models\SiteUriModel;
use putyourlightson\blitz\services\CacheRequestService;
use yii\base\ErrorException;
use yii\base\Exception;
use yii\base\InvalidArgumentException;
use yii\log\Logger;

/**
 * @property-read null|string $settingsHtml
 */
class FileStorage extends BaseCacheStorage
{
    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('blitz', 'Blitz File Storage (recommended)');
    }

    /**
     * @var string The storage folder path.
     */
    public string $folderPath = '@webroot/cache/blitz';

    /**
     * @var bool Whether Gzip files should be created.
     */
    public bool $createGzipFiles = false;

    /**
     * @var bool Whether Brotli files should be created.
     */
    public bool $createBrotliFiles = false;

    /**
     * @var bool Whether cached files may be counted.
     */
    public bool $countCachedFiles = true;

    /**
     * @var string|null
     */
    private ?string $_cacheFolderPath;

    /**
     * @var array|null
     */
    private ?array $_sitePaths;

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();

        if (!empty($this->folderPath)) {
            $this->_cacheFolderPath = FileHelper::normalizePath(
                App::parseEnv($this->folderPath)
            );
        }
    }

    /**
     * @inheritdoc
     */
    public function get(SiteUriModel $siteUri): string
    {
        $filePaths = $this->getFilePaths($siteUri);

        foreach ($filePaths as $filePath) {
            if (is_file($filePath)) {
                return file_get_contents($filePath);
            }
        }

        return '';
    }

    /**
     * @inheritdoc
     */
    public function save(string $value, SiteUriModel $siteUri, int $duration = null): void
    {
        $filePaths = $this->getFilePaths($siteUri);

        if (empty($filePaths)) {
            return;
        }

        try {
            foreach ($filePaths as $filePath) {
                FileHelper::writeToFile($filePath, $value);

                if ($this->createGzipFiles && function_exists('gzencode')) {
                    FileHelper::writeToFile($filePath . '.gz', gzencode($value));
                }

                if ($this->createBrotliFiles && function_exists('brotli_compress')) {
                    /** @noinspection PhpUndefinedFunctionInspection */
                    FileHelper::writeToFile($filePath . '.br', brotli_compress($value));
                }
            }
        } catch (Exception|ErrorException|InvalidArgumentException $exception) {
            Blitz::$plugin->log($exception->getMessage(), [], Logger::LEVEL_ERROR);
        }
    }

    /**
     * @inheritdoc
     */
    public function deleteUris(array $siteUris): void
    {
        $event = new RefreshCacheEvent(['siteUris' => $siteUris]);
        $this->trigger(self::EVENT_BEFORE_DELETE_URIS, $event);

        if (!$event->isValid) {
            return;
        }

        foreach ($siteUris as $siteUri) {
            $filePaths = $this->getFilePaths($siteUri);

            foreach ($filePaths as $filePath) {
                // Delete file if it exists
                if (is_file($filePath)) {
                    unlink($filePath);
                }

                if (is_file($filePath . '.gz')) {
                    unlink($filePath . '.gz');
                }
            }
        }

        if ($this->hasEventHandlers(self::EVENT_AFTER_DELETE_URIS)) {
            $this->trigger(self::EVENT_AFTER_DELETE_URIS, $event);
        }
    }

    /**
     * @inheritdoc
     */
    public function deleteAll(): void
    {
        $event = new RefreshCacheEvent();
        $this->trigger(self::EVENT_BEFORE_DELETE_ALL, $event);

        if (!$event->isValid) {
            return;
        }

        if (empty($this->_cacheFolderPath)) {
            return;
        }

        try {
            FileHelper::removeDirectory($this->_cacheFolderPath);
        } catch (ErrorException $exception) {
            Blitz::$plugin->log($exception->getMessage(), [], Logger::LEVEL_ERROR);
        }

        if ($this->hasEventHandlers(self::EVENT_AFTER_DELETE_ALL)) {
            $this->trigger(self::EVENT_AFTER_DELETE_ALL, $event);
        }
    }

    /**
     * @inheritdoc
     */
    public function getUtilityHtml(): string
    {
        if ($this->countCachedFiles === false) {
            return '';
        }

        return Craft::$app->getView()->renderTemplate('blitz/_drivers/storage/file/utility', [
            'sites' => $this->_getSitePageCount(),
        ]);
    }

    /**
     * @inheritdoc
     */
    public function getWidgetHtml(): string
    {
        if ($this->countCachedFiles === false) {
            return '';
        }

        return Craft::$app->getView()->renderTemplate('blitz/_drivers/storage/file/widget', [
            'sites' => $this->_getSitePageCount(),
        ]);
    }

    /**
     * @inheritdoc
     */
    public function getSettingsHtml(): ?string
    {
        return Craft::$app->getView()->renderTemplate('blitz/_drivers/storage/file/settings', [
            'driver' => $this,
        ]);
    }

    /**
     * Returns file paths for the provided site ID and URI.
     *
     * @return string[]
     */
    public function getFilePaths(SiteUriModel $siteUri): array
    {
        $sitePath = $this->getSitePath($siteUri->siteId);

        if ($sitePath == '') {
            return [];
        }

        if ($this->_hasInvalidQueryString($siteUri->uri)) {
            return [];
        }

        $filePath = $this->_getNormalizedFilePath($sitePath, $siteUri->uri);

        // Ensure that file path is a sub path of the site path
        if (!str_contains($filePath, $sitePath)) {
            return [];
        }

        $filePaths = [$filePath];

        /**
         * If the filename includes URL encoded characters, create a copy with the characters decoded.
         * Use `rawurldecode()` which does not decode plus symbols ('+') into spaces (`urldecode()` does).
         *
         * Solves:
         * https://github.com/putyourlightson/craft-blitz/issues/222
         * https://github.com/putyourlightson/craft-blitz/issues/224
         * https://github.com/putyourlightson/craft-blitz/issues/252
         *
         * Similar issue: https://www.drupal.org/project/boost/issues/1398578
         * Solution: https://www.drupal.org/files/issues/boost-n1398578-19.patch
         */
        $decodedUri = rawurldecode($siteUri->uri);
        if ($decodedUri != $siteUri->uri) {
            $filePaths[] = $this->_getNormalizedFilePath($sitePath, $decodedUri);
        }

        return $filePaths;
    }

    /**
     * Returns site path from provided site ID.
     */
    public function getSitePath(int $siteId): string
    {
        if (!empty($this->_sitePaths[$siteId])) {
            return $this->_sitePaths[$siteId];
        }

        if (empty($this->_cacheFolderPath)) {
            return '';
        }

        // Get the site host and path from the site's base URL
        $site = Craft::$app->getSites()->getSiteById($siteId);
        $siteUrl = Craft::getAlias($site->getBaseUrl());
        $siteHostPath = preg_replace('/^(http|https):\/\//i', '', $siteUrl);

        // Remove colons in path, for port numbers for example
        // https://github.com/putyourlightson/craft-blitz/issues/369
        $siteHostPath = str_replace(':', '', $siteHostPath);

        $this->_sitePaths[$siteId] = FileHelper::normalizePath($this->_cacheFolderPath . '/' . $siteHostPath);

        return $this->_sitePaths[$siteId];
    }

    /**
     * Returns the number of cached pages in the provided path.
     */
    public function getCachedPageCount(string $path): int
    {
        if (!$this->countCachedFiles) {
            return 0;
        }

        if (!is_dir($path)) {
            return 0;
        }

        return count(FileHelper::findFiles($path, [
            'except' => [CacheRequestService::CACHED_INCLUDE_PATH . '/'],
            'only' => ['index.html'],
        ]));
    }

    /**
     * Returns the number of cached includes in the provided path.
     */
    public function getCachedIncludeCount(string $path): int
    {
        if (!$this->countCachedFiles) {
            return 0;
        }

        $path = rtrim($path, '/') . '/' . CacheRequestService::CACHED_INCLUDE_PATH;

        if (!is_dir($path)) {
            return 0;
        }

        return count(FileHelper::findFiles($path, [
            'only' => ['index.html'],
        ]));
    }

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        return [
            [['folderPath'], 'required'],
        ];
    }

    private function _getSitePageCount(): array
    {
        $sites = [];

        $allSites = Craft::$app->getSites()->getAllSites();

        foreach ($allSites as $site) {
            $sitePath = $this->getSitePath($site->id);

            $sites[$site->id] = [
                'name' => $site->name,
                'path' => $sitePath,
                'pageCount' => $this->getCachedPageCount($sitePath),
                'includeCount' => $this->getCachedIncludeCount($sitePath),
            ];
        }

        foreach ($sites as $siteId => &$site) {
            // Check if other site page counts should be reduced from this site
            foreach ($sites as $otherSiteId => $otherSite) {
                if ($otherSiteId == $siteId) {
                    continue;
                }

                if (str_starts_with($otherSite['path'], $site['path'] . '/')) {
                    $site['pageCount'] -= is_dir($otherSite['path']) ? $otherSite['pageCount'] : 0;
                }
            }
        }

        return $sites;
    }

    private function _getNormalizedFilePath(string $sitePath, string $uri): string
    {
        $uriParts = explode('?', $uri);
        $queryString = $uriParts[1] ?? '';

        // Encode forward slashes and square brackets in query string
        $queryString = str_replace(['/', '[', ']'], ['%2F', '%5B', '%5D'], $queryString);

        $uri = $uriParts[0] . '/' . $queryString;

        return FileHelper::normalizePath($sitePath . '/' . $uri . '/index.html');
    }

    private function _hasInvalidQueryString(string $uri): bool
    {
        if (!str_contains($uri, '?')) {
            return false;
        }

        // Ensure that the query string path is at least one level deep
        // https://github.com/putyourlightson/craft-blitz/issues/343
        $queryString = substr($uri, strpos($uri, '?') + 1);
        $queryString = rawurldecode($queryString);
        $queryStringPath = FileHelper::normalizePath($queryString);

        if (empty($queryStringPath) || str_starts_with($queryStringPath, '.')) {
            return true;
        }

        return false;
    }
}
