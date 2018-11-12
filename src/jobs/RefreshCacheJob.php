<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\jobs;

use Craft;
use craft\elements\db\ElementQuery;
use craft\helpers\App;
use craft\helpers\UrlHelper;
use craft\queue\BaseJob;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\models\SettingsModel;
use putyourlightson\blitz\records\CacheRecord;
use putyourlightson\blitz\records\ElementCacheRecord;
use putyourlightson\blitz\records\ElementQueryCacheRecord;

class RefreshCacheJob extends BaseJob
{
    // Properties
    // =========================================================================

    /**
     * @var int
     */
    public $elementId;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     * @throws \Exception
     * @throws \Throwable
     */
    public function execute($queue)
    {
        App::maxPowerCaptain();

        // Get cache IDs to clear
        $cacheIds = [];

        // Get element cache records grouped by cache ID
        $elementCacheRecords = ElementCacheRecord::find()
            ->select('cacheId')
            ->where(['elementId' => $this->elementId])
            ->groupBy('cacheId')
            ->all();

        /** @var ElementCacheRecord[] $elementCacheRecords */
        foreach ($elementCacheRecords as $elementCacheRecord) {
            if (!in_array($elementCacheRecord->cacheId, $cacheIds, true)) {
                $cacheIds[] = $elementCacheRecord->cacheId;
            }
        }

        // Get element query cache records of the element type with a cache ID that has not already been stored
        $elementQueryCacheRecords = ElementQueryCacheRecord::find()
            ->select('cacheId, query')
            ->where(['not', ['cacheId' => $cacheIds]])
            ->andWhere(['type' => Craft::$app->getElements()->getElementTypeById($this->elementId)])
            ->all();

        /** @var ElementQueryCacheRecord[] $elementQueryCacheRecords */
        foreach ($elementQueryCacheRecords as $elementQueryCacheRecord) {
            if (!in_array($elementQueryCacheRecord->cacheId, $cacheIds, true)) {
                /** @var ElementQuery|false $query */
                /** @noinspection UnserializeExploitsInspection */
                $query = @unserialize(base64_decode($elementQueryCacheRecord->query));

                if ($query === false || in_array($this->elementId, $query->ids(), true)) {
                    $cacheIds[] = $elementQueryCacheRecord->cacheId;
                }
            }
        }

        // Get URLs of caches to clear
        $urls = [];

        /** @var CacheRecord[] $cacheRecords */
        $cacheRecords = CacheRecord::find()
            ->select('id, uri, siteId')
            ->where(['id' => $cacheIds])
            ->all();

        foreach ($cacheRecords as $cacheRecord) {
            $url = UrlHelper::siteUrl($cacheRecord->uri, null, null, $cacheRecord->siteId);
            $urls[] = $url;

            // Delete cached file so we get a fresh file cache
            Blitz::$plugin->cache->deleteFileByUri($cacheRecord->siteId, $cacheRecord->uri);

            // Delete cache record so we get a fresh element cache table
            $cacheRecord->delete();
        }

        /** @var SettingsModel $settings */
        $settings = Blitz::$plugin->getSettings();

        if ($settings->cachingEnabled AND $settings->warmCacheAutomatically AND count($urls) > 0) {
            Craft::$app->getQueue()->push(new WarmCacheJob(['urls' => $urls]));
        }

        return;
    }

    // Protected Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    protected function defaultDescription(): string
    {
        return Craft::t('blitz', 'Refreshing Blitz cache');
    }
}
