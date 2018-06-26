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

        return FileHelper::findDirectories($cacheFolderPath);
    }

    /**
     * Caches the template if the URI is a match
     *
     * @param string $uri
     * @param string $output
     */
    public function cacheOutputIfUrlMatch(string $uri, string $output)
    {
        /** @var SettingsModel $settings */
        $settings = Blitz::$plugin->getSettings();

        // Excluded URI patterns take priority
        foreach ($settings->excludeUriPatterns as $excludeUriPattern) {
            if (preg_match('/'.trim($excludeUriPattern[0], '/').'/', $uri)) {
                return;
            }
        }

        foreach ($settings->includeUriPatterns as $includeUriPattern) {
            if (preg_match('/'.trim($includeUriPattern[0], '/').'/', $uri)) {
                if (!empty($settings->cacheFolderPath)) {
                    FileHelper::writeToFile(FileHelper::normalizePath(Craft::getAlias($settings->cacheFolderPath).$uri).'.html', $output);
                }

                return;
            }
        }
    }
}
