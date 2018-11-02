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
use putyourlightson\blitz\records\ElementCacheRecord;
use putyourlightson\blitz\records\ElementQueryCacheRecord;
use yii\db\ActiveQuery;

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

        $urls = [];

        /** @var ElementCacheRecord[] $elementCacheRecords */
        $elementCacheRecords = ElementCacheRecord::find()
            ->select('cacheId')
            ->with('cache')
            ->where(['elementId' => $this->elementId])
            ->groupBy('cacheId')
            ->all();

        foreach ($elementCacheRecords as $elementCacheRecord) {
            $url = UrlHelper::siteUrl($elementCacheRecord->cache->uri, null, null, $elementCacheRecord->cache->siteId);

            if (!in_array($url, $urls, true)) {
                $urls[] = $url;
            }

            // Delete cached file so we get a fresh file cache
            Blitz::$plugin->cache->deleteFileByUri($elementCacheRecord->cache->siteId, $elementCacheRecord->cache->uri);

            // Delete cache record so we get a fresh element cache table
            $elementCacheRecord->cache->delete();
        }

        /** @var ElementQueryCacheRecord[] $elementQueryCacheRecords */
        $elementQueryCacheRecords = ElementQueryCacheRecord::find()
            ->select('cacheId, query')
            ->with([
                'cache' => function(ActiveQuery $query) {
                    $query->select('id, siteId, uri');
                }
            ])
            ->where(['type' => Craft::$app->getElements()->getElementTypeById($this->elementId)])
            ->all();

        foreach ($elementQueryCacheRecords as $elementQueryCacheRecord) {
            /** @var ElementQuery|false $query */
            /** @noinspection UnserializeExploitsInspection */
            $query = @unserialize(base64_decode($elementQueryCacheRecord->query));

            if ($query === false || in_array($this->elementId, $query->ids(), true)) {
                $url = UrlHelper::siteUrl($elementQueryCacheRecord->cache->uri, null, null, $elementQueryCacheRecord->cache->siteId);

                if (!in_array($url, $urls, true)) {
                    $urls[] = $url;
                }

                // Delete cached file so we get a fresh file cache
                Blitz::$plugin->cache->deleteFileByUri($elementQueryCacheRecord->cache->siteId, $elementQueryCacheRecord->cache->uri);

                // Delete cache record so we get a fresh element cache table
                $elementQueryCacheRecord->cache->delete();
            }
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
