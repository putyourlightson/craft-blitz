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
use craft\helpers\FileHelper;
use craft\helpers\UrlHelper;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\jobs\CacheJob;
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
    /**
     * @var bool|null
     */
    private $_isCacheableRequest;

    // Public Methods
    // =========================================================================

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
     * @param string $uri
     * @return string
     */
    public function uriToFilePath(string $uri): string
    {
        $cacheFolderPath = $this->getCacheFolderPath();

        if ($cacheFolderPath == '') {
            return '';
        }

        // Replace __home__ with blank string
        $uri = ($uri == '__home__' ? '' : $uri);

        // Replace ? with / in URI
        $uri = str_replace('?', '/', $uri);

        return FileHelper::normalizePath($cacheFolderPath.'/'.Craft::$app->getRequest()->getHostName().'/'.$uri.'/index.html');
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
        $cacheRecord = $this->_getOrCreateCacheRecord($siteId, $uri);

        /** @var Element $element */
        $values = [
            'cacheId' => $cacheRecord->id,
            'type' => $elementQuery->elementType,
        ];

        // Based on code from includeElementQueryInTemplateCaches method in \craft\servicesTemplateCaches-
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
     * @param string $uri
     */
    public function cacheOutput(string $output, string $uri)
    {
        // Ignore URIs that begin with /index.php
        if (strpos($uri, '/index.php') === 0) {
            return;
        }

        $filePath = $this->uriToFilePath($uri);

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
     * Caches by an element ID
     *
     * @param int $elementId
     */
    public function cacheByElementId(int $elementId)
    {
        $urls = [];

        /** @var ElementCacheRecord[] $elementCacheRecords */
        $elementCacheRecords = $this->_getElementCacheRecords($elementId);

        foreach ($elementCacheRecords as $elementCacheRecord) {
            $urls[] = UrlHelper::siteUrl($elementCacheRecord->cache->uri, null, null, $elementCacheRecord->cache->siteId);

            // Delete cached file so we get a fresh file cache
            $this->_deleteFileByUri($elementCacheRecord->cache->uri);

            // Delete cache record so we get a fresh element cache table
            $elementCacheRecord->cache->delete();
        }

        /** @var ElementQueryCacheRecord[] $elementQueryCacheRecords */
        $elementQueryCacheRecords = $this->_getElementQueryCacheRecords($elementId);

        foreach ($elementQueryCacheRecords as $elementQueryCacheRecord) {
            /** @var ElementQuery|false $query */
            /** @noinspection UnserializeExploitsInspection */
            $query = @unserialize(base64_decode($elementQueryCacheRecord->query));

            if ($query === false || in_array($elementId, $query->ids(), true)) {
                $url = UrlHelper::siteUrl($elementQueryCacheRecord->cache->uri, null, null, $elementQueryCacheRecord->cache->siteId);

                if (!in_array($url, $urls, true)) {
                    $urls[] = $url;
                }

                // Delete cached file so we get a fresh file cache
                $this->_deleteFileByUri($elementQueryCacheRecord->cache->uri);

                // Delete cache record so we get a fresh element cache table
                $elementQueryCacheRecord->cache->delete();
            }
        }

        if (!Blitz::$plugin->getSettings()->cachingEnabled) {
            return;
        }

        if (count($urls)) {
            Craft::$app->getQueue()->push(new CacheJob(['urls' => $urls]));
        }
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
                Craft::$app->getQueue()->push(new CacheJob(['urls' => $urls]));

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

        return preg_match('#'.trim($pattern, '/').'#', $uri);
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

    /**
     * Returns element cache records for a given element ID
     *
     * @param int $elementId
     * @return ElementCacheRecord[]
     */
    private function _getElementCacheRecords(int $elementId): array
    {
        return ElementCacheRecord::find()
            ->select('cacheId')
            ->with('cache')
            ->where(['elementId' => $elementId])
            ->groupBy('cacheId')
            ->all();
    }

    /**
     * Returns element query cache records for a given element ID
     *
     * @param int $elementId
     * @return ElementQueryCacheRecord[]
     */
    private function _getElementQueryCacheRecords(int $elementId): array
    {
        return ElementQueryCacheRecord::find()
            ->select('cacheId')
            ->with('cache')
            ->where(['type' => Craft::$app->getElements()->getElementTypeById($elementId)])
            ->groupBy('cacheId')
            ->all();
    }

    /**
     * Deletes a file for a given URI
     *
     * @param string $uri
     */
    private function _deleteFileByUri(string $uri)
    {
        $filePath = $this->uriToFilePath($uri);

        // Delete file if it exists
        if (is_file($filePath)) {
            @unlink($filePath);
        }
    }
}
