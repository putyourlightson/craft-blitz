<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\services;

use Craft;
use craft\base\Component;
use craft\base\Element;
use craft\base\ElementInterface;
use craft\elements\db\ElementQuery;
use craft\elements\GlobalSet;
use craft\elements\MatrixBlock;
use craft\helpers\FileHelper;
use craft\helpers\UrlHelper;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\events\RegisterNonCacheableElementTypesEvent;
use putyourlightson\blitz\jobs\RefreshCacheJob;
use putyourlightson\blitz\jobs\WarmCacheJob;
use putyourlightson\blitz\models\SettingsModel;
use putyourlightson\blitz\records\CacheRecord;
use putyourlightson\blitz\records\ElementCacheRecord;
use putyourlightson\blitz\records\ElementQueryCacheRecord;
use yii\base\ErrorException;

/**
 *
 * @property bool $isCacheableRequest
 * @property string $cacheFolderPath
 */
class CacheService extends Component
{
    // Constants
    // =========================================================================

    /**
     * @event RegisterNonCacheableElementTypesEvent
     */
    const EVENT_REGISTER_NON_CACHEABLE_ELEMENT_TYPES = 'registerNonCacheableElementTypes';

    // Properties
    // =========================================================================

    /**
     * @var bool|null
     */
    private $_isCacheableRequest;

    // Public Methods
    // =========================================================================

    /**
     * Returns non cacheable element types
     *
     * @return string[]
     */
    public function getNonCacheableElementTypes(): array
    {
        $elementTypes = [
            GlobalSet::class,
            MatrixBlock::class,
        ];

        $event = new RegisterNonCacheableElementTypesEvent([
            'elementTypes' => $elementTypes,
        ]);
        $this->trigger(self::EVENT_REGISTER_NON_CACHEABLE_ELEMENT_TYPES, $event);

        return $event->elementTypes;
    }

    /**
     * Returns whether the request is cacheable
     *
     * @return bool
     */
    public function getIsCacheableRequest(): bool
    {
        if ($this->_isCacheableRequest !== null) {
            return $this->_isCacheableRequest;
        }

        $this->_isCacheableRequest = $this->_checkIsCacheableRequest();

        return $this->_isCacheableRequest;
    }

    /**
     * Returns whether the URI is cacheable
     *
     * @param string $uri
     * @return bool
     */
    public function getIsCacheableUri(string $uri): bool
    {
        /** @var SettingsModel $settings */
        $settings = Blitz::$plugin->getSettings();

        if (!$settings->queryStringCachingEnabled && mb_strpos($uri, '?') !== false) {
            return false;
        }

        // Excluded URI patterns take priority
        if (is_array($settings->excludedUriPatterns)) {
            foreach ($settings->excludedUriPatterns as $excludedUriPattern) {
                // Normalize to string
                if (is_array($excludedUriPattern)) {
                    $excludedUriPattern = $excludedUriPattern[0];
                }

                if ($this->_matchUriPattern($excludedUriPattern, $uri)) {
                    return false;
                }
            }
        }

        if (is_array($settings->includedUriPatterns)) {
            foreach ($settings->includedUriPatterns as $includedUriPattern) {
                // Normalize to string
                if (is_array($includedUriPattern)) {
                    $includedUriPattern = $includedUriPattern[0];
                }

                if ($this->_matchUriPattern($includedUriPattern, $uri)) {
                    return true;
                }
            }
        }

        return false;
    }

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
     * Converts URI to file path
     *
     * @param int $siteId
     * @param string $uri
     * @return string
     */
    public function uriToFilePath(int $siteId, string $uri): string
    {
        $cacheFolderPath = $this->getCacheFolderPath();

        if ($cacheFolderPath == '') {
            return '';
        }

        // Get the site host and path from the site's base URL
        $site = Craft::$app->getSites()->getSiteById($siteId);
        $siteUrl = Craft::getAlias($site->baseUrl);
        $siteHostPath = preg_replace('/https?:\/\//', '', $siteUrl);

        // Replace __home__ with blank string
        $uri = ($uri == '__home__' ? '' : $uri);

        // Replace ? with / in URI
        $uri = str_replace('?', '/', $uri);

        return FileHelper::normalizePath($cacheFolderPath.'/'.$siteHostPath.'/'.$uri.'/index.html');
    }

    /**
     * Adds an element cache to the database
     *
     * @param ElementInterface $element
     * @param int $siteId
     * @param string $uri
     */
    public function addElementCache(ElementInterface $element, int $siteId, string $uri)
    {
        // Don't proceed if this is a non cacheable element type
        if (in_array(get_class($element), $this->getNonCacheableElementTypes(), true)) {
            return;
        }

        $cacheRecord = $this->_getOrCreateCacheRecord($siteId, $uri);

        /** @var Element $element */
        $values = [
            'cacheId' => $cacheRecord->id,
            'elementId' => $element->id,
        ];

        $elementCacheRecordCount = ElementCacheRecord::find()
            ->where($values)
            ->count();

        if ($elementCacheRecordCount == 0) {
            $elementCacheRecord = new ElementCacheRecord($values);
            $elementCacheRecord->save();
        }
    }

    /**
     * Adds an element query cache to the database
     *
     * @param ElementQuery $elementQuery
     * @param int $siteId
     * @param string $uri
     */
    public function addElementQueryCache(ElementQuery $elementQuery, int $siteId, string $uri)
    {
        // Don't proceed if this is a non cacheable element type
        if (in_array($elementQuery->elementType, $this->getNonCacheableElementTypes(), true)) {
            return;
        }

        $cacheRecord = $this->_getOrCreateCacheRecord($siteId, $uri);

        /** @var Element $element */
        $values = [
            'cacheId' => $cacheRecord->id,
            'type' => $elementQuery->elementType,
        ];

        // Based on code from includeElementQueryInTemplateCaches method in \craft\servicesTemplateCaches
        $query = $elementQuery->query;
        $subQuery = $elementQuery->subQuery;
        $customFields = $elementQuery->customFields;

        // Nullify values
        $elementQuery->query = null;
        $elementQuery->subQuery = null;
        $elementQuery->customFields = null;

        // We need to base64-encode the string so db\Connection::quoteSql() doesn't tweak any of the table/columns names
        $values['query'] = base64_encode(serialize($elementQuery));

        // Set back to original values
        $elementQuery->query = $query;
        $elementQuery->subQuery = $subQuery;
        $elementQuery->customFields = $customFields;

        $elementQueryCacheRecordCount = ElementQueryCacheRecord::find()
            ->where($values)
            ->count();

        if ($elementQueryCacheRecordCount == 0) {
            $elementQueryCacheRecord = new ElementQueryCacheRecord($values);
            $elementQueryCacheRecord->save();
        }
    }

    /**
     * Caches the output to a URI
     *
     * @param string $output
     * @param int $siteId
     * @param string $uri
     */
    public function cacheOutput(string $output, int $siteId, string $uri)
    {
        // If the URI represents an element then add it to the element cache
        $element = Craft::$app->getElements()->getElementByUri($uri, $siteId, true);

        if ($element !== null) {
            $this->addElementCache($element, $siteId, $uri);
        }

        // Ignore URIs that begin with /index.php
        if (strpos($uri, '/index.php') === 0) {
            return;
        }

        $filePath = $this->uriToFilePath($siteId, $uri);

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
     * Caches by an element
     *
     * @param Element $element
     */
    public function cacheByElement(Element $element)
    {
        /** @var SettingsModel $settings */
        $settings = Blitz::$plugin->getSettings();

        // Clear and warm the cache if this is a global set element as they are populated on every request
        if ($element instanceof GlobalSet) {
            $this->clearCache();

            if ($settings->cachingEnabled AND $settings->warmCacheAutomatically) {
                $this->warmCache(true);
            }

            return;
        }

        // Don't proceed if this is a non cacheable element type
        if (in_array(get_class($element), $this->getNonCacheableElementTypes(), true)) {
            return;
        }

        // Delete the cached file immediately if this element has a URI
        if ($element->uri !== null) {
            $this->deleteFileByUri($element->siteId, $element->uri);
        }

        Craft::$app->getQueue()->push(new RefreshCacheJob(['elementId' => $element->id]));
    }

    /**
     * Clears all cache
     */
    public function clearCache()
    {
        /** @var SettingsModel $settings */
        $settings = Blitz::$plugin->getSettings();

        if (empty($settings->cacheFolderPath)) {
            return;
        }

        try {
            FileHelper::removeDirectory(FileHelper::normalizePath(Craft::getAlias('@webroot').'/'.$settings->cacheFolderPath));
        }
        catch (ErrorException $e) {}
    }

    /**
     * Clears cache record
     *
     * @param int $siteId
     * @param string $uri
     */
    public function clearCacheRecord(int $siteId, string $uri)
    {
        CacheRecord::deleteAll([
            'siteId' => $siteId,
            'uri' => $uri,
        ]);
    }

    /**
     * Warms cache
     *
     * @param bool $queue
     * @return int
     */
    public function warmCache(bool $queue = false): int
    {
        /** @var SettingsModel $settings */
        $settings = Blitz::$plugin->getSettings();

        if (empty($settings->cacheFolderPath)) {
            return 0;
        }

        $this->clearCache();

        $count = 0;
        $urls = [];

        // Get URLs from all cache records
        $cacheRecords = CacheRecord::find()
            // ID is required for later deleting record
            ->select(['id', 'siteId', 'uri'])
            ->all();

        /** @var CacheRecord $cacheRecord */
        foreach ($cacheRecords as $cacheRecord) {
            if ($this->getIsCacheableUri($cacheRecord->uri)) {
                $urls[] = UrlHelper::siteUrl($cacheRecord->uri, null, null, $cacheRecord->siteId);
            }

            // Delete cache record so we get a fresh cache
            $cacheRecord->delete();
        }

        // Get URLs from all element types
        $elementTypes = Craft::$app->getElements()->getAllElementTypes();

        /** @var Element $elementType */
        foreach ($elementTypes as $elementType) {
            if ($elementType::hasUris()) {
                // Loop through all sites to ensure we warm all site element URLs
                $sites = Craft::$app->getSites()->getAllSites();

                foreach ($sites as $site) {
                    $elements = $elementType::find()->siteId($site->id)->all();

                    /** @var Element $element */
                    foreach ($elements as $element) {
                        $uri = trim($element->uri, '/');
                        $uri = ($uri == '__home__' ? '' : $uri);

                        if ($uri !== null && $this->getIsCacheableUri($uri)) {
                            $url = $element->getUrl();

                            if ($url !== null && !in_array($url, $urls, true)) {
                                $urls[] = $url;
                            }
                        }
                    }
                }
            }
        }

        if (count($urls) > 0)
        {
            if ($queue === true ) {
                Craft::$app->getQueue()->push(new WarmCacheJob(['urls' => $urls]));

                return 0;
            }

            $client = new Client();

            foreach ($urls as $url) {
                try {
                    $response = $client->get($url);

                    $count++;
                }
                catch (ClientException $e) {}
                catch (RequestException $e) {}
            }
        }

        return $count;
    }

    /**
     * Deletes a file for a given site and URI
     *
     * @param int $siteId
     * @param string $uri
     */
    public function deleteFileByUri(int $siteId, string $uri)
    {
        $filePath = $this->uriToFilePath($siteId, $uri);

        // Delete file if it exists
        if (is_file($filePath)) {
            @unlink($filePath);
        }
    }

    // Private Methods
    // =========================================================================

    /**
     * Checks if the request is cacheable
     *
     * @return bool
     */
    private function _checkIsCacheableRequest(): bool
    {
        $request = Craft::$app->getRequest();
        $response = Craft::$app->getResponse();

        // Ensure this is a front-end get that is not a console request or an action request or live preview and returns status 200
        if (!$request->getIsSiteRequest() || !$request->getIsGet() || $request->getIsActionRequest() || $request->getIsLivePreview() || !$response->getIsOk()) {
            return false;
        }

        /** @var SettingsModel $settings */
        $settings = Blitz::$plugin->getSettings();

        if (!$settings->cachingEnabled) {
            return false;
        }

        return true;
    }

    /**
     * Matches a URI pattern
     *
     * @param string $pattern
     * @param string $uri
     * @return bool
     */
    private function _matchUriPattern(string $pattern, string $uri): bool
    {
        if ($pattern == '') {
            return false;
        }

        return preg_match('#'.trim($pattern, '/').'#', trim($uri, '/'));
    }

    /**
     * Returns a cache record or creates it if it doesn't exist
     *
     * @param int $siteId
     * @param string $uri
     * @return CacheRecord
     */
    private function _getOrCreateCacheRecord(int $siteId, string $uri): CacheRecord
    {
        $values = [
            'siteId' => $siteId,
            'uri' => $uri,
        ];

        $cacheRecord = CacheRecord::find()
            ->select('id')
            ->where($values)
            ->one();

        if ($cacheRecord === null) {
            $cacheRecord = new CacheRecord($values);
            $cacheRecord->save();
        }

        return $cacheRecord;
    }
}
