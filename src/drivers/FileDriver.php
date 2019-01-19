<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\drivers;

use Craft;
use craft\helpers\FileHelper;
use Symfony\Component\Filesystem\Exception\IOException;
use yii\base\ErrorException;
use yii\base\InvalidArgumentException;
use yii\log\Logger;

/**
 * @property mixed $settingsHtml
 */
class FileDriver extends BaseDriver
{
    // Static
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('blitz', 'Blitz File Driver');
    }

    // Properties
    // =========================================================================

    /**
     * @var string
     */
    public $folderPath = 'cache/blitz';

    /**
     * @var string|null
     */
    private $_cacheFolderPath;

    /**
     * @var array|null
     */
    private $_siteCacheCount;

    /**
     * @var array|null
     */
    private $_sitePaths;

    /**
     * @var array|null
     */
    private $_filePaths;

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
                Craft::getAlias('@webroot').'/'.Craft::parseEnv($this->folderPath)
            );
        }
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['folderPath'], 'required'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function getCachedUri(int $siteId, string $uri): string
    {
        $value = '';

        $filePath = $this->getFilePath($siteId, $uri);

        if (is_file($filePath)) {
            $value = file_get_contents($filePath);
        }

        return $value;
    }

    /**
     * @inheritdoc
     */
    public function getCacheCount(int $siteId): int
    {
        if (!empty($this->_siteCacheCount[$siteId])) {
            return $this->_siteCacheCount[$siteId];
        }

        $sitePath = $this->getSitePath($siteId);

        $count = is_dir($sitePath) ? count(FileHelper::findFiles($sitePath)) : 0;

        // Check if other site counts should be reduced from this site
        $sites = Craft::$app->getSites()->getAllSites();

        foreach ($sites as $site) {
            if ($site->id == $siteId) {
                continue;
            }

            $otherPath = $this->getSitePath($site->id);

            if (strpos($otherPath, $sitePath) === 0) {
                $count = $count - (is_dir($otherPath) ? $this->getCacheCount($site->id) : 0);
            }
        }

        $this->_siteCacheCount[$siteId] = $count;

        return $count;
    }

    /**
     * @inheritdoc
     */
    public function saveCache(string $value, int $siteId, string $uri)
    {
        $filePath = $this->getFilePath($siteId, $uri);

        if (!empty($filePath)) {
            // Append timestamp
            $value .= '<!-- Cached by Blitz on '.date('c').' -->';

            // Force UTF8 encoding as per https://stackoverflow.com/a/9047876
            $value = "\xEF\xBB\xBF".$value;

            try {
                FileHelper::writeToFile($filePath, $value);
            }
            catch (ErrorException $e) {
                Craft::getLogger()->log($e->getMessage(), Logger::LEVEL_ERROR, 'blitz');
            }
            catch (InvalidArgumentException $e) {
                Craft::getLogger()->log($e->getMessage(), Logger::LEVEL_ERROR, 'blitz');
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function clearCache()
    {
        if (empty($this->_cacheFolderPath)) {
            return;
        }

        try {
            FileHelper::removeDirectory($this->_cacheFolderPath);
        }
        catch (ErrorException $e) {
            Craft::getLogger()->log($e->getMessage(), Logger::LEVEL_ERROR, 'blitz');
        }
        catch (IOException $e) {
            Craft::getLogger()->log($e->getMessage(), Logger::LEVEL_ERROR, 'blitz');
        }
    }

    /**
     * @inheritdoc
     */
    public function clearCachedUri(int $siteId, string $uri)
    {
        $filePath = $this->getFilePath($siteId, $uri);

        // Delete file if it exists
        if (is_file($filePath)) {
            @unlink($filePath);
        }
    }

    /**
     * @inheritdoc
     */
    public function getSettingsHtml()
    {
        return Craft::$app->getView()->renderTemplate('blitz/_drivers/file/settings', [
            'driver' => $this,
        ]);
    }

    // Helper Methods
    // =========================================================================

    /**
     * Returns file path from provided site ID and URI.
     *
     * @param int $siteId
     * @param string $uri
     *
     * @return string
     */
    public function getFilePath(int $siteId, string $uri): string
    {
        if (!empty($this->_filePaths[$siteId][$uri])) {
            return $this->_filePaths[$siteId][$uri];
        }

        $sitePath = $this->getSitePath($siteId);

        if ($sitePath == '') {
            return '';
        }

        // Replace __home__ with blank string
        $uri = ($uri == '__home__' ? '' : $uri);

        // Replace ? with / in URI
        $uri = str_replace('?', '/', $uri);

        $this->_filePaths[$siteId][$uri] = FileHelper::normalizePath($sitePath.'/'.$uri.'/index.html');

        return $this->_filePaths[$siteId][$uri];
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
        $siteUrl = Craft::getAlias($site->baseUrl);
        $siteHostPath = preg_replace('/^(http|https):\/\//i', '', $siteUrl);

        $this->_sitePaths[$siteId] = FileHelper::normalizePath($this->_cacheFolderPath.'/'.$siteHostPath);

        return $this->_sitePaths[$siteId];
    }
}