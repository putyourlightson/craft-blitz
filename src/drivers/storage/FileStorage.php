<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\drivers\storage;

use Craft;
use craft\helpers\FileHelper;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\models\SiteUriModel;
use yii\base\ErrorException;
use yii\base\InvalidArgumentException;

/**
 * @property mixed $settingsHtml
 */
class FileStorage extends BaseCacheStorage
{
    // Static
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('blitz', 'Blitz File Storage (recommended)');
    }

    // Properties
    // =========================================================================

    /**
     * @var string
     */
    public $folderPath = '@webroot/cache/blitz';

    /**
     * @var bool
     */
    public $createGzipFiles = false;

    /**
     * @var bool
     */
    public $createBrotliFiles = false;

    /**
     * @var bool
     */
    public $countCachedFiles = true;

    /**
     * @var string|null
     */
    private $_cacheFolderPath;

    /**
     * @var array|null
     */
    private $_sitePaths;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();

        if (!empty($this->folderPath)) {
            $this->_cacheFolderPath = FileHelper::normalizePath(
                Craft::parseEnv($this->folderPath)
            );
        }
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            [['folderPath'], 'required'],
        ];
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
    public function save(string $value, SiteUriModel $siteUri)
    {
        $filePaths = $this->getFilePaths($siteUri);

        if (empty($filePaths)) {
            return;
        }

        try {
            foreach ($filePaths as $filePath) {
                FileHelper::writeToFile($filePath, $value);

                if ($this->createGzipFiles) {
                    FileHelper::writeToFile($filePath.'.gz', gzencode($value));
                }

                if ($this->createBrotliFiles && function_exists('brotli_compress')) {
                    FileHelper::writeToFile($filePath.'.br', brotli_compress($value) );
                }
            }
        }
        catch (ErrorException $e) {
            Blitz::$plugin->log($e->getMessage(), [], 'error');
        }
        catch (InvalidArgumentException $e) {
            Blitz::$plugin->log($e->getMessage(), [], 'error');
        }
    }

    /**
     * @inheritdoc
     */
    public function deleteUris(array $siteUris)
    {
        foreach ($siteUris as $siteUri) {
            $filePaths = $this->getFilePaths($siteUri);

            foreach ($filePaths as $filePath) {
                // Delete file if it exists
                if (is_file($filePath)) {
                    unlink($filePath);
                }

                if (is_file($filePath.'.gz')) {
                    unlink($filePath.'.gz');
                }
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function deleteAll()
    {
        if (empty($this->_cacheFolderPath)) {
            return;
        }

        try {
            FileHelper::removeDirectory($this->_cacheFolderPath);
        }
        catch (ErrorException $e) {
            Blitz::$plugin->log($e->getMessage(), [], 'error');
        }
    }

    /**
     * @inheritdoc
     */
    public function getUtilityHtml(): string
    {
        $sites = [];

        $allSites = Craft::$app->getSites()->getAllSites();

        foreach ($allSites as $site) {
            $sitePath = $this->getSitePath($site->id);

            $sites[$site->id] = [
                'name' => $site->name,
                'path' => $sitePath,
                'count' => $this->getCachedFileCount($sitePath),
            ];
        }

        foreach ($sites as $siteId => &$site) {
            // Check if other site counts should be reduced from this site
            foreach ($sites as $otherSiteId => $otherSite) {
                if ($otherSiteId == $siteId) {
                    continue;
                }

                if (strpos($otherSite['path'], $site['path'].'/') === 0) {
                    $site['count'] -= is_dir($otherSite['path']) ? $otherSite['count'] : 0;
                }
            }
        }

        return Craft::$app->getView()->renderTemplate('blitz/_drivers/storage/file/utility', [
            'sites' => $sites,
        ]);
    }

    /**
     * @inheritdoc
     */
    public function getSettingsHtml()
    {
        return Craft::$app->getView()->renderTemplate('blitz/_drivers/storage/file/settings', [
            'driver' => $this,
        ]);
    }

    /**
     * Returns file paths for the provided site ID and URI.
     *
     * @param SiteUriModel $siteUri
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

        // Replace ? with / in URI
        $uri = str_replace('?', '/', $siteUri->uri);

        // Create normalized file path
        $filePath = FileHelper::normalizePath($sitePath.'/'.$uri.'/index.html');

        // Ensure that file path is a sub path of the site path
        if (strpos($filePath, $sitePath) === false) {
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
        if (rawurldecode($filePath) != $filePath) {
            $filePaths[] = rawurldecode($filePath);
        }

        return $filePaths;
    }

    /**
     * Returns site path from provided site ID.
     *
     * @param int $siteId
     *
     * @return string
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

        $this->_sitePaths[$siteId] = FileHelper::normalizePath($this->_cacheFolderPath.'/'.$siteHostPath);

        return $this->_sitePaths[$siteId];
    }

    /**
     * Returns the number of cached files in the provided path.
     *
     * @param string $path
     *
     * @return int|string
     */
    public function getCachedFileCount(string $path)
    {
        if (!$this->countCachedFiles) {
            return '-';
        }

        return is_dir($path) ? count(FileHelper::findFiles($path, ['only' => ['index.html']])) : 0;
    }

    private function _hasInvalidQueryString(string $uri): bool
    {
        if (strpos($uri, '?') === false) {
            return false;
        }

        // Ensure that the query string path is at least one level deep
        // https://github.com/putyourlightson/craft-blitz/issues/343
        $queryString = substr($uri, strpos($uri, '?') + 1);
        $queryString = rawurldecode($queryString);
        $queryStringPath = FileHelper::normalizePath($queryString);

        if (empty($queryStringPath) || substr($queryStringPath, 0, 1) == '.') {
            return true;
        }

        return false;
    }
}
