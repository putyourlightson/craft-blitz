<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\services;

use Craft;
use craft\base\Component;
use craft\base\Element;
use craft\helpers\FileHelper;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\jobs\CacheJob;
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
        $cacheFolderPath = $this->_getSiteCacheFolderPath();

        if ($cacheFolderPath == '' || !is_dir($cacheFolderPath)) {
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
            if ($this->_matchUriPattern($excludeUriPattern[0], $uri)) {
                return false;
            }
        }

        foreach ($settings->includeUriPatterns as $includeUriPattern) {
            if ($this->_matchUriPattern($includeUriPattern[0], $uri)) {
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
        $cacheFolderPath = $this->_getSiteCacheFolderPath();

        if ($cacheFolderPath == '') {
            return '';
        }

        return $cacheFolderPath.'/'.$uri.'.html';
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

    /**
     * Cache by element
     *
     * @param Element $element
     */
    public function cacheByElement(Element $element)
    {
        if (Blitz::$plugin->getSettings()->cachingEnabled === false) {
            return;
        }

        $elementIds = [];

        $relatedElements = $this->_getRelatedElements($element);

        /** @var Element $relatedElement */
        foreach ($relatedElements as $relatedElement) {
            if ($relatedElement::hasUris()) {
                $elementIds[] = $relatedElement->id;
            }
        }

        if (count($elementIds)) {
            Craft::$app->getQueue()->push(new CacheJob(['elementIds' => $elementIds]));
        }
    }

    /**
     * Clears cache by element
     *
     * @param Element $element
     */
    public function clearCacheByElement(Element $element)
    {
        $relatedElements = $this->_getRelatedElements($element);

        /** @var Element $relatedElement */
        foreach ($relatedElements as $relatedElement) {
            if ($relatedElement::hasUris()) {
                $filePath = $this->uriToFilePath($relatedElement->uri);

                // Delete file if it exists
                if (is_file($filePath)) {
                    unlink($filePath);
                }
            }
        }
    }

    // Private Methods
    // =========================================================================

    /**
     * @return string
     */
    private function _getSiteCacheFolderPath(): string
    {
        /** @var SettingsModel $settings */
        $settings = Blitz::$plugin->getSettings();

        if (empty($settings->cacheFolderPath)) {
            return '';
        }

        $hostName = Craft::$app->getRequest()->getHostName();

        return FileHelper::normalizePath(Craft::getAlias('@webroot').'/'.$settings->cacheFolderPath.'/'.$hostName);
    }

    /**
     * @param string $pattern
     * @param string $uri
     * @return bool
     */
    private function _matchUriPattern(string $pattern, string $uri): bool
    {
        if ($pattern == '') {
            return false;
        }

        return preg_match('#'.trim($pattern, '/').'#', $uri);
    }

    /**
     * @param Element $element
     * @return Element[]
     */
    private function _getRelatedElements(Element $element): array
    {
        $relatedElements = [$element];

        $elementTypes = Craft::$app->getElements()->getAllElementTypes();

        /** @var Element $elementType */
        foreach ($elementTypes as $elementType) {
            if ($elementType::hasUris()) {
                $relatedElementsOfType = $elementType::find()
                    ->relatedTo($element)
                    ->all();

                $relatedElements = array_merge($relatedElements, $relatedElementsOfType);
            }
        }

        return $relatedElements;
    }
}
