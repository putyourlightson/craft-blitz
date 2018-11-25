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
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\events\RegisterNonCacheableElementTypesEvent;
use putyourlightson\blitz\jobs\RefreshCacheJob;
use putyourlightson\blitz\jobs\WarmCacheJob;
use putyourlightson\blitz\models\SettingsModel;
use putyourlightson\blitz\records\CacheRecord;
use putyourlightson\blitz\records\ElementCacheRecord;
use putyourlightson\blitz\records\ElementQueryCacheRecord;
use putyourlightson\blitz\records\ElementQueryRecord;
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
    private $_nonCacheableElementTypes;

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
     * Returns whether the request is cacheable
     *
     * @return bool
     */
    public function getIsCacheableRequest(): bool
    {
        $request = Craft::$app->getRequest();
        $response = Craft::$app->getResponse();

        // Ensure this is a front-end get that is not a console request or an action request or live preview and returns status 200
        if (!$request->getIsSiteRequest() || !$request->getIsGet() || $request->getIsActionRequest() || $request->getIsLivePreview() || !$response->getIsOk()) {
            return false;
        }

        $user = Craft::$app->getUser()->getIdentity();

        // Ensure that if user is logged in then debug toolbar is not enabled
        if ($user !== null && $user->getPreference('enableDebugToolbarForSite')) {
            return false;
        }

        if (!$this->_settings->cachingEnabled) {
            return false;
        }

        if ($this->_settings->queryStringCaching == 0 && $request->getQueryStringWithoutPath() !== '') {
            return false;
        }

        return true;
    }

    /**
     * Returns whether the URI is cacheable
     *
     * @param int $siteId
     * @param string $uri
     *
     * @return bool
     */
    public function getIsCacheableUri(int $siteId, string $uri): bool
    {
        // Excluded URI patterns take priority
        if (is_array($this->_settings->excludedUriPatterns)) {
            foreach ($this->_settings->excludedUriPatterns as $excludedUriPattern) {
                if ($this->_matchUriPattern($excludedUriPattern, $siteId, $uri)) {
                    return false;
                }
            }
        }

        if (is_array($this->_settings->includedUriPatterns)) {
            foreach ($this->_settings->includedUriPatterns as $includedUriPattern) {
                if ($this->_matchUriPattern($includedUriPattern, $siteId, $uri)) {
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
     * Returns non cacheable element types
     *
     * @return string[]
     */
    public function getNonCacheableElementTypes(): array
    {
        if ($this->_nonCacheableElementTypes !== null) {
            return $this->_nonCacheableElementTypes;
        }

        $elementTypes = [
            GlobalSet::class,
            MatrixBlock::class,
        ];

        $event = new RegisterNonCacheableElementTypesEvent([
            'elementTypes' => $elementTypes,
        ]);
        $this->trigger(self::EVENT_REGISTER_NON_CACHEABLE_ELEMENT_TYPES, $event);

        $this->_nonCacheableElementTypes = $event->elementTypes;

        return $this->_nonCacheableElementTypes;
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
        $siteHostPath = preg_replace('/https?:\/\//', '', $siteUrl);

        // Replace __home__ with blank string
        $uri = ($uri == '__home__' ? '' : $uri);

        // Replace ? with / in URI
        $uri = str_replace('?', '/', $uri);

        $this->_filePaths[$siteId][$uri] = FileHelper::normalizePath($cacheFolderPath.'/'.$siteHostPath.'/'.$uri.'/index.html');

        return $this->_filePaths[$siteId][$uri];
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

        $cacheId = $this->_getOrCreateCacheId($siteId, $uri);

        // Don't proceed if element caching is disabled
        if (!$this->_settings->cacheElements) {
            return;
        }

        /** @var Element $element */
        $values = [
            'cacheId' => $cacheId,
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

        $cacheId = $this->_getOrCreateCacheId($siteId, $uri);

        // Don't proceed if element query caching is disabled
        if (!$this->_settings->cacheElementQueries) {
            return;
        }

        // Based on code from includeElementQueryInTemplateCaches method in \craft\services\TemplateCaches)
        $query = $elementQuery->query;
        $subQuery = $elementQuery->subQuery;
        $customFields = $elementQuery->customFields;

        // Nullify values
        $elementQuery->query = null;
        $elementQuery->subQuery = null;
        $elementQuery->customFields = null;

        // Base64-encode the query so db\Connection::quoteSql() doesn't tweak any of the table/columns names
        $values = [
            'cacheId' => $cacheId,
            'type' => $elementQuery->elementType,
            'query' => base64_encode(serialize($elementQuery))
        ];

        // Set back to original values
        $elementQuery->query = $query;
        $elementQuery->subQuery = $subQuery;
        $elementQuery->customFields = $customFields;

        // If a record with the values does not exist then create a new one
        /** @var ElementQueryCacheRecord $elementQueryCacheRecord */
        $elementQueryCacheRecord = ElementQueryCacheRecord::find()
            ->joinWith('elementQuery', false, 'INNER JOIN')
            ->where($values)
            ->one();

        if ($elementQueryCacheRecord === null) {
            $elementQueryCacheRecord = new ElementQueryCacheRecord(['cacheId' => $cacheId]);

            // Remove cacheId from values
            unset($values['cacheId']);

            // If an element query record with the values does not exist then create a new one
            $elementQueryRecord = ElementQueryRecord::find()
                ->where($values)
                ->one();

            if ($elementQueryRecord === null) {
                $elementQueryRecord = new ElementQueryRecord($values);
                $elementQueryRecord->save();
            }

            $elementQueryCacheRecord->queryId = $elementQueryRecord->id;
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
        // Get the URI path
        $uriPath = preg_replace('/\?.*/', '', $uri);

        // If the URI path represents an element then add the full URI to the element cache
        $element = Craft::$app->getElements()->getElementByUri(trim($uriPath, '/'), $siteId, true);

        if ($element !== null) {
            $this->addElementCache($element, $siteId, $uri);
        }

        // Ignore URIs that begin with /index.php
        if (strpos($uri, '/index.php') === 0) {
            return;
        }

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
     * Caches by an element
     *
     * @param ElementInterface $element
     */
    public function cacheByElement(ElementInterface $element)
    {
        // Clear and warm the cache if this is a global set element as they are populated on every request
        if ($element instanceof GlobalSet) {
            $this->clearFileCache();

            if ($this->_settings->cachingEnabled AND $this->_settings->warmCacheAutomatically) {
                Craft::$app->getQueue()->push(new WarmCacheJob(['urls' => $this->prepareWarmCacheUrls()]));
            }

            return;
        }

        // Don't proceed if this is a non cacheable element type
        if (in_array(get_class($element), $this->getNonCacheableElementTypes(), true)) {
            return;
        }

        // Delete the cached file immediately if this element has a URI
        /** @var Element $element */
        if ($element->uri !== null) {
            $this->deleteFileByUri($element->siteId, $element->uri);
        }

        Craft::$app->getQueue()->push(new RefreshCacheJob(['elementId' => $element->id]));
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
     * Cleans element query table
     */
    public function cleanElementQueryTable()
    {
        // Get element query records without an associated element query cache
        $elementQueryRecords = ElementQueryRecord::find()
            ->joinWith('elementQueryCache')
            ->where(['cacheId' => null])
            ->all();

        foreach ($elementQueryRecords as $elementQueryRecord) {
            $elementQueryRecord->delete();
        }
    }

    /**
     * Prepares cache for warming and returns URLS to warm
     *
     * @return string[]
     */
    public function prepareWarmCacheUrls(): array
    {
        if (empty($this->_settings->cacheFolderPath)) {
            return [];
        }

        $this->clearFileCache();

        $count = 0;
        $urls = [];

        // Get URLs from all cache records
        $cacheRecords = CacheRecord::find()
            // ID is required for later deleting record
            ->select(['id', 'siteId', 'uri'])
            ->all();

        /** @var CacheRecord $cacheRecord */
        foreach ($cacheRecords as $cacheRecord) {
            if ($this->getIsCacheableUri($cacheRecord->siteId, $cacheRecord->uri)) {
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

                        if ($uri !== null && $this->getIsCacheableUri($site->id, $uri)) {
                            $url = $element->getUrl();

                            if ($url !== null && !in_array($url, $urls, true)) {
                                $urls[] = $url;
                            }
                        }
                    }
                }
            }
        }

        return $urls;
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

    // Private Methods
    // =========================================================================

    /**
     * Matches a URI pattern
     *
     * @param array $pattern
     * @param int $siteId
     * @param string $uri
     *
     * @return bool
     */
    private function _matchUriPattern(array $pattern, int $siteId, string $uri): bool
    {
        // Return false if site is not empty and does not match the provided site ID
        if (!empty($pattern[1]) && $pattern[1] != $siteId) {
            return false;
        }

        $uriPattern = $pattern[0];

        if ($uriPattern == '') {
            return false;
        }

        // Replace "*" with 0 or more characters as otherwise it'll throw an error
        if ($uriPattern == '*') {
            $uriPattern = '.*';
        }

        return preg_match('#'.trim($uriPattern, '/').'#', trim($uri, '/'));
    }

    /**
     * Returns a cache record ID or creates it if it doesn't exist
     *
     * @param int $siteId
     * @param string $uri
     *
     * @return int
     */
    private function _getOrCreateCacheId(int $siteId, string $uri): int
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

        return $cacheRecord->id;
    }
}
