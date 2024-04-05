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
 * directly, provided it accepts encoding.
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
     * @var string
     */
    public string $cacheComponent = 'cache';

    /**
     * @var CacheInterface|null
     */
    private ?CacheInterface $cache;

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

        $this->cache = Craft::$app->get($this->cacheComponent, false);
    }

    /**
     * @inheritdoc
     */
    public function get(SiteUriModel $siteUri): ?string
    {
        if ($this->canCompressCachedValues()) {
            $value = $this->getCompressed($siteUri);

            if (!empty($value)) {
                $value = gzdecode($value);

                if ($value) {
                    return $value;
                }
            }
        }

        $key = $this->getKey($siteUri);

        return $this->getFromCache($key);
    }

    /**
     * @inheritdoc
     */
    public function getCompressed(SiteUriModel $siteUri): ?string
    {
        $key = $this->getKey($siteUri, true);

        return $this->getFromCache($key);
    }

    /**
     * @inheritdoc
     */
    public function save(string $value, SiteUriModel $siteUri, int $duration = null, bool $allowEncoding = true): void
    {
        if ($this->cache === null) {
            return;
        }

        $key = $this->getKey($siteUri);

        if ($allowEncoding && $this->canCompressCachedValues()) {
            $key = $this->getKey($siteUri, true);
            $value = gzencode($value);
        }

        $this->cache->set($key, $value, $duration);
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

        if ($this->cache === null) {
            return;
        }

        foreach ($siteUris as $siteUri) {
            $this->delete($siteUri);
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

        if ($this->cache === null) {
            return;
        }

        $this->cache->flush();

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

    /**
     * Returns a key from the site URI and an encoded boolean.
     */
    private function getKey(SiteUriModel $siteUri, bool $encoded = false): array
    {
        // Cast the site ID to an integer to avoid an incorrect key
        // https://github.com/putyourlightson/craft-blitz/issues/257
        $key = [self::KEY_PREFIX, (int)$siteUri->siteId, $siteUri->uri];

        if ($encoded) {
            $key[] = BaseCacheStorage::ENCODING;
        }

        return $key;
    }

    /**
     * Returns a key’s value from the cache.
     */
    private function getFromCache(array $key): ?string
    {
        if ($this->cache === null) {
            return null;
        }

        // Redis cache can throw an exception if the connection is broken
        try {
            $value = $this->cache->get($key);

            if ($value !== false) {
                return $value;
            }
        } /** @noinspection PhpRedundantCatchClauseInspection */
        catch (Exception) {
        }

        return null;
    }

    /**
     * Deletes the cached values for a site URI.
     */
    private function delete(SiteUriModel $siteUri): void
    {
        $this->cache->delete($this->getKey($siteUri));
        $this->cache->delete($this->getKey($siteUri, true));
    }
}
