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

/**
 *
 * @property array $cacheFolders
 */
class CacheService extends Component
{
    // Public Methods
    // =========================================================================

    /**
     * Returns the existing cache folders
     *
     * @return array
     */
    public function getCacheFolders(): array
    {
        /** @var SettingsModel $settings */
        $settings = Blitz::$plugin->getSettings();

        if (empty($settings->cacheFolderPath)) {
            return [];
        }

        $cacheFolderPath = FileHelper::normalizePath(Craft::getAlias($settings->cacheFolderPath));

        if (!is_dir($cacheFolderPath)) {
            return [];
        }

        $cacheFolders = [];

        foreach (FileHelper::findDirectories($cacheFolderPath) as $cacheFolder) {
            $cacheFolders[] = [
                'value' => $cacheFolder,
                'fileCount' => count(FileHelper::findFiles($cacheFolder)),
            ];
        }

        return $cacheFolders;
    }

    /**
     * Checks if the request is cacheable
     */
    public function isCacheableRequest(): bool
    {
        $request = Craft::$app->getRequest();
        $uri = $request->getUrl();

        if (!$request->getIsSiteRequest() || $request->getIsLivePreview()) {
            return false;
        }

        /** @var SettingsModel $settings */
        $settings = Blitz::$plugin->getSettings();

        // Excluded URI patterns take priority
        foreach ($settings->excludeUriPatterns as $excludeUriPattern) {
            if (preg_match('/'.trim($excludeUriPattern[0], '/').'/', $uri)) {
                return false;
            }
        }

        foreach ($settings->includeUriPatterns as $includeUriPattern) {
            if (preg_match('/'.trim($includeUriPattern[0], '/').'/', $uri)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Converts URI to file path
     *
     * @param string $uri
     * @return string
     */
    public function uriToFilePath(string $uri): string
    {
        /** @var SettingsModel $settings */
        $settings = Blitz::$plugin->getSettings();

        if (empty($settings->cacheFolderPath)) {
            return '';
        }

        return FileHelper::normalizePath(Craft::getAlias($settings->cacheFolderPath).$uri).'.html';
    }

    /**
     * Caches the output to a URI
     *
     * @param string $uri
     * @param string $output
     */
    public function cacheOutput(string $uri, string $output)
    {
        $filePath = $this->uriToFilePath($uri);

        if (!empty($filePath)) {
            FileHelper::writeToFile($filePath, $output);
        }
    }
}
