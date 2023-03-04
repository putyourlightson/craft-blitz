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
 * Uses Craft’s cache storage method, especially useful for distributed systems.
 * The cached values can be compressed if gzip is supported, to help reduce the
 * memory required for storage. While gzip is not as fast as other compression
 * algorithms such as Snappy and LZO, it is more widely supported and accepted
 * by most browsers. This allows us to return compressed values to the browser
 * directly, provided it accepts gzip encoding.
 * https://docs.redis.com/latest/ri/memory-optimizations/#compress-values
 *
 * @property-read string|null $settingsHtml
 */
class YiiCacheStorage extends BaseCacheStorage
{
    /**
     * @const string
     */
    public const KEY_PREFIX = 'blitz';

    /**
     * @const string
     */
    public const ENCODING = 'gzip';

    /**
     * @var string
     */
    public string $cacheComponent = 'cache';

    /**
     * @var bool Whether cached values should be compressed using gzip.
     */
    public bool $compressCachedValues = false;

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

        $key = $this->_getKey($siteUri);

        if ($this->canCompressCachedValues()) {
            $key[] = self::ENCODING;
            $value = $this->_getFromCache($key);

            if ($value) {
                $value = gzdecode($value);
            }
        } else {
            $value = $this->_cache->get($key);
        }

        return $value ?: '';
    }

    /**
     * @inheritdoc
     */
    public function getWithEncoding(SiteUriModel $siteUri, array $encodings = []): array
    {
        if ($this->_cache === null) {
            return [null, null];
        }

        if ($this->canCompressCachedValues() && in_array(self::ENCODING, $encodings)) {
            $key = $this->_getKey($siteUri);
            $key[] = self::ENCODING;
            $value = $this->_getFromCache($key);

            if ($value) {
                return [$value, self::ENCODING];
            }
        }

        return [$this->get($siteUri), null];
    }

    /**
     * @inheritdoc
     */
    public function save(string $value, SiteUriModel $siteUri, int $duration = null): void
    {
        if ($this->_cache === null) {
            return;
        }

        $key = $this->_getKey($siteUri);

        if ($this->canCompressCachedValues()) {
            $key[] = self::ENCODING;
            $value = gzencode($value);
        }

        $this->_cache->set($key, $value, $duration);
    }

    /**
     * @inheritdoc
     */
    public function deleteUris(array $siteUris): void
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
            $this->_cache->delete($this->_getKey($siteUri));
        }

        if ($this->hasEventHandlers(self::EVENT_AFTER_DELETE_URIS)) {
            $this->trigger(self::EVENT_AFTER_DELETE_URIS, $event);
        }
    }

    /**
     * @inheritdoc
     */
    public function deleteAll(): void
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
    public function getSettingsHtml(): ?string
    {
        return Craft::$app->getView()->renderTemplate('blitz/_drivers/storage/yii-cache/settings', [
            'driver' => $this,
            'compressionSupported' => function_exists('gzencode'),
        ]);
    }

    public function canCompressCachedValues(): bool
    {
        return $this->compressCachedValues && function_exists('gzencode');
    }

    /**
     * Returns a key from the site URI.
     */
    private function _getKey(SiteUriModel $siteUri): array
    {
        // Cast the site ID to an integer to avoid an incorrect key
        // https://github.com/putyourlightson/craft-blitz/issues/257
        return [self::KEY_PREFIX, (int)$siteUri->siteId, $siteUri->uri];
    }

    /**
     * Returns a key’s value from the cache.
     */
    private function _getFromCache(array $key): string
    {
        // Redis cache can throw an exception if the connection is broken
        try {
            $value = $this->_cache->get($key);

            if ($value) {
                return $value;
            }
        } /** @noinspection PhpRedundantCatchClauseInspection */
        catch (Exception) {
        }

        return '';
    }
}
