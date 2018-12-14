<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\jobs;

use Craft;
use craft\helpers\App;
use craft\helpers\UrlHelper;
use craft\queue\BaseJob;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\records\CacheRecord;

class RefreshCacheJob extends BaseJob
{
    // Properties
    // =========================================================================

    /**
     * @var int[]
     */
    public $cacheIds;

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

        // Get URLs of caches to clear
        $urls = [];

        /** @var CacheRecord[] $cacheRecords */
        $cacheRecords = CacheRecord::find()
            ->select('uri, siteId')
            ->where(['id' => $this->cacheIds])
            ->all();

        $total = count($cacheRecords);
        $count = 0;

        foreach ($cacheRecords as $cacheRecord) {
            $url = UrlHelper::siteUrl($cacheRecord->uri, null, null, $cacheRecord->siteId);
            $urls[] = $url;

            // Delete cached file so we get a fresh file cache
            Blitz::$plugin->file->deleteFileByUri($cacheRecord->siteId, $cacheRecord->uri);

            $count++;
            $this->setProgress($queue, $count / $total);
        }

        Blitz::$plugin->cache->afterRefreshCache($this->cacheIds);

        // Delete cache records so we get a fresh element cache table
        CacheRecord::deleteAll(['id' => $this->cacheIds]);

        $settings = Blitz::$plugin->getSettings();

        if ($settings->cachingEnabled AND $settings->warmCacheAutomatically) {
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
