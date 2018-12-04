<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\services;

use Craft;
use craft\base\Component;
use craft\helpers\FileHelper;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\models\SettingsModel;
use yii\base\ErrorException;

/**
 *
 * @property string $cacheFolderPath
 */
class FileService extends Component
{
    // Properties
    // =========================================================================

    /**
     * @var SettingsModel
     */
    private $_settings;

    /**
     * @var string|null
     */
    private $_cacheFolderPath;

    /**
     * @var array|null
     */
    private $_filePaths;

    // Public Methods
    // =========================================================================

    public function init()
    {
        parent::init();

        $this->_settings = Blitz::$plugin->getSettings();
    }

    /**
     * Returns the cache folder path
     *
     * @return string
     */
    public function getCacheFolderPath(): string
    {
        if ($this->_cacheFolderPath !== null) {
            return $this->_cacheFolderPath;
        }

        if (empty($this->_settings->cacheFolderPath)) {
            $this->_cacheFolderPath = '';
        }
        else {
            $this->_cacheFolderPath = FileHelper::normalizePath(Craft::getAlias('@webroot').'/'.$this->_settings->cacheFolderPath);
        }

        return $this->_cacheFolderPath;
    }

    /**
     * Returns file path from provided site ID and URI
     *
     * @param int $siteId
     * @param string $uri
     * @return string
     */
    public function getFilePath(int $siteId, string $uri): string
    {
        if (!empty($this->_filePaths[$siteId][$uri])) {
            return $this->_filePaths[$siteId][$uri];
        }

        $cacheFolderPath = $this->getCacheFolderPath();

        if ($cacheFolderPath == '') {
            return '';
        }

        // Get the site host and path from the site's base URL
        $site = Craft::$app->getSites()->getSiteById($siteId);
        $siteUrl = Craft::getAlias($site->baseUrl);
        $siteHostPath = preg_replace('/^(http|https):\/\//i', '', $siteUrl);

        // Replace __home__ with blank string
        $uri = ($uri == '__home__' ? '' : $uri);

        // Replace ? with / in URI
        $uri = str_replace('?', '/', $uri);

        $this->_filePaths[$siteId][$uri] = FileHelper::normalizePath($cacheFolderPath.'/'.$siteHostPath.'/'.$uri.'/index.html');

        return $this->_filePaths[$siteId][$uri];
    }

    /**
     * Caches the output to a file
     *
     * @param string $output
     * @param int $siteId
     * @param string $uri
     */
    public function cacheToFile(string $output, int $siteId, string $uri)
    {
        $filePath = $this->getFilePath($siteId, $uri);

        if (!empty($filePath)) {
            // Append timestamp
            $output .= '<!-- Cached by Blitz on '.date('c').' -->';

            // Force UTF8 encoding as per https://stackoverflow.com/a/9047876
            $output = "\xEF\xBB\xBF".$output;

            try {
                FileHelper::writeToFile($filePath, $output);
            }
            catch (ErrorException $e) {}
        }
    }

    /**
     * Clears file cache
     */
    public function clearFileCache()
    {
        if (empty($this->_settings->cacheFolderPath)) {
            return;
        }

        try {
            FileHelper::removeDirectory(FileHelper::normalizePath(Craft::getAlias('@webroot').'/'.$this->_settings->cacheFolderPath));
        }
        catch (ErrorException $e) {}
    }

    /**
     * Deletes a file for a given site and URI
     *
     * @param int $siteId
     * @param string $uri
     */
    public function deleteFileByUri(int $siteId, string $uri)
    {
        $filePath = $this->getFilePath($siteId, $uri);

        // Delete file if it exists
        if (is_file($filePath)) {
            @unlink($filePath);
        }
    }
}
