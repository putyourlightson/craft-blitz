<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\services;

use Craft;
use craft\base\Component;
use craft\base\Element;
use craft\base\ElementInterface;
use craft\helpers\FileHelper;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\jobs\CacheJob;
use putyourlightson\blitz\models\SettingsModel;

/**
 *
 * @property string $cacheFolderPath
 */
class CacheService extends Component
{
    // Public Methods
    // =========================================================================

    /**
     * Returns the cache folder path
     *
     * @return string
     */
    public function getCacheFolderPath(): string
    {
        /** @var SettingsModel $settings */
        $settings = Blitz::$plugin->getSettings();

        if (empty($settings->cacheFolderPath)) {
            return '';
        }

        return FileHelper::normalizePath(Craft::getAlias('@webroot').'/'.$settings->cacheFolderPath);
    }

    /**
     * Checks if the request is cacheable
     */
    public function isCacheableRequest(): bool
    {
        /** @var SettingsModel $settings */
        $settings = Blitz::$plugin->getSettings();

        if (!$settings->cachingEnabled) {
            return false;
        }

        $request = Craft::$app->getRequest();
        $response = Craft::$app->getResponse();
        $uri = $request->getUrl();

        // Check for front-end get request with status 200 that is not an action request or live preview
        if (!$request->getIsSiteRequest() || !$request->getIsGet() || !$response->getIsOk() || $request->getIsActionRequest() || $request->getIsLivePreview()) {
            return false;
        }

        // Excluded URI patterns take priority
        if (is_array($settings->excludedUriPatterns)) {
            foreach ($settings->excludedUriPatterns as $excludedUriPattern) {
                if ($this->_matchUriPattern($excludedUriPattern[0], $uri)) {
                    return false;
                }
            }
        }

        if (is_array($settings->includedUriPatterns)) {
            foreach ($settings->includedUriPatterns as $includedUriPattern) {
                if ($this->_matchUriPattern($includedUriPattern[0], $uri)) {
                    return true;
                }
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
        $cacheFolderPath = $this->getCacheFolderPath();

        if ($cacheFolderPath == '') {
            return '';
        }

        $uri = $uri === '__home__' ? '' : $uri;

        return FileHelper::normalizePath($cacheFolderPath.'/'.Craft::$app->getRequest()->getHostName().'/'.$uri.'/index.html');
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
            $output .= '<!-- Cached by Blitz '.date('c').' -->';
            FileHelper::writeToFile($filePath, $output);
        }
    }

    /**
     * Cache by element
     *
     * @param ElementInterface $element
     */
    public function cacheByElement(ElementInterface $element)
    {
        if (!Blitz::$plugin->getSettings()->cachingEnabled) {
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
     * @param ElementInterface $element
     */
    public function clearCacheByElement(ElementInterface $element)
    {
        $relatedElements = $this->_getRelatedElements($element);

        /** @var Element $relatedElement */
        foreach ($relatedElements as $relatedElement) {
            if ($relatedElement::hasUris()) {
                if (empty($relatedElement->uri)) {
                    continue;
                }
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
     * @param ElementInterface $element
     * @return Element[]
     */
    private function _getRelatedElements(ElementInterface $element): array
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
