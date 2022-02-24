<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\drivers\storage;

use Craft;
use putyourlightson\blitz\events\RefreshCacheEvent;
use putyourlightson\blitz\models\SiteUriModel;
use yii\caching\CacheInterface;
use yii\db\Exception;

/**
 * @property mixed $settingsHtml
 */
class YiiCacheStorage extends BaseCacheStorage
{
    /**
     * @const string
     */
    public const KEY_PREFIX = 'blitz';

    /**
     * @var string
     */
    public string $cacheComponent = 'cache';

    /**
     * @var CacheInterface|null
     */
    private ?CacheInterface $_cache;

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('blitz', 'Yii Cache Storage');
    }

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();

        $this->_cache = Craft::$app->get($this->cacheComponent, false);
    }

    /**
     * @inheritdoc
     */
    public function get(SiteUriModel $siteUri): string
    {
        if ($this->_cache === null) {
            return '';
        }

        $value = '';

        // Redis cache throws an exception if the connection is broken, so we catch it here
        try {
            // Cast the site ID to an integer to avoid an incorrect key
            // https://github.com/putyourlightson/craft-blitz/issues/257
            $value = $this->_cache->get([
                self::KEY_PREFIX, (int)$siteUri->siteId, $siteUri->uri
            ]);
        }
        catch (Exception) {}

        return $value ?: '';
    }

    /**
     * @inheritdoc
     */
    public function save(string $value, SiteUriModel $siteUri, int $duration = null)
    {
        if ($this->_cache === null) {
            return;
        }

        // Cast the site ID to an integer to avoid an incorrect key
        // https://github.com/putyourlightson/craft-blitz/issues/257
        $this->_cache->set([
            self::KEY_PREFIX, (int)$siteUri->siteId, $siteUri->uri
        ], $value, $duration);
    }

    /**
     * @inheritdoc
     */
    public function deleteUris(array $siteUris)
    {
        $event = new RefreshCacheEvent(['siteUris' => $siteUris]);
        $this->trigger(self::EVENT_BEFORE_DELETE_URIS, $event);

        if (!$event->isValid) {
            return;
        }

        if ($this->_cache === null) {
            return;
        }

        foreach ($siteUris as $siteUri) {
            // Cast the site ID to an integer to avoid an incorrect key
            // https://github.com/putyourlightson/craft-blitz/issues/257
            $this->_cache->delete([
                self::KEY_PREFIX, (int)$siteUri->siteId, $siteUri->uri
            ]);
        }

        if ($this->hasEventHandlers(self::EVENT_AFTER_DELETE_URIS)) {
            $this->trigger(self::EVENT_AFTER_DELETE_URIS, $event);
        }
    }

    /**
     * @inheritdoc
     */
    public function deleteAll()
    {
        $event = new RefreshCacheEvent();
        $this->trigger(self::EVENT_BEFORE_DELETE_ALL, $event);

        if (!$event->isValid) {
            return;
        }

        if ($this->_cache === null) {
            return;
        }

        $this->_cache->flush();

        if ($this->hasEventHandlers(self::EVENT_AFTER_DELETE_ALL)) {
            $this->trigger(self::EVENT_AFTER_DELETE_ALL, $event);
        }
    }

    /**
     * @inheritdoc
     */
    public function getUtilityHtml(): string
    {
        return Craft::$app->getView()->renderTemplate('blitz/_drivers/storage/yii-cache/utility');
    }

    /**
     * @inheritdoc
     */
    public function getSettingsHtml(): ?string
    {
        return Craft::$app->getView()->renderTemplate('blitz/_drivers/storage/yii-cache/settings', [
            'driver' => $this,
        ]);
    }
}
